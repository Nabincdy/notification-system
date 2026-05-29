<?php


namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\UnsupportedNotificationChannelException;
use App\Managers\NotificationChannelManager;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessNotificationJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $notificationId,
    ) {}

    /**
     * Unique key to prevent duplicate job processing.
     */
    public function uniqueId(): string
    {
        return "process-notification:{$this->notificationId}";
    }

    /**
     * Unique job lock duration in seconds.
     */
    public function uniqueFor(): int
    {
        return 60;
    }

    /**
     * Exponential backoff: 10s, 30s, 90s, 270s, 810s
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 90, 270, 810];
    }

    /**
     * Tags for queue monitoring.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return [
            'notifications',
            "notification:{$this->notificationId}",
        ];
    }

    public function handle(
        NotificationService       $service,
        NotificationChannelManager $channelManager,
        NotificationRepositoryInterface $repository,
    ): void {
        $notification = $repository->findById($this->notificationId);

        if ($notification === null) {
            Log::warning('ProcessNotificationJob: notification not found', [
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        // Idempotency guard — skip already processed
        if ($notification->isProcessed()) {
            Log::info('ProcessNotificationJob: skipping already processed notification', [
                'notification_id' => $this->notificationId,
            ]);
            return;
        }

        $service->incrementAttempts($notification);
        $notification->refresh();

        $service->markAsProcessing($notification);
        $notification->refresh();

        Log::info('ProcessNotificationJob: processing', [
            'notification_id' => $this->notificationId,
            'attempt'         => $notification->attempts,
            'type'            => $notification->type->value,
        ]);

        try {
            $channel = $channelManager->resolveFor($notification);
            $channel->send($notification);
            $service->markAsProcessed($notification);

            Log::info('ProcessNotificationJob: processed successfully', [
                'notification_id' => $this->notificationId,
            ]);
        } catch (UnsupportedNotificationChannelException $e) {
            // Non-retryable — permanently fail
            Log::error('ProcessNotificationJob: unsupported channel, failing permanently', [
                'notification_id' => $this->notificationId,
                'error'           => $e->getMessage(),
            ]);

            $service->markAsFailed($notification);
            $this->fail($e);
        } catch (Throwable $e) {
            Log::warning('ProcessNotificationJob: failed attempt, will retry', [
                'notification_id' => $this->notificationId,
                'attempt'         => $notification->attempts,
                'error'           => $e->getMessage(),
            ]);

            // Reset to pending so it can be re-picked
            $service->markAsProcessing($notification);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ProcessNotificationJob: exhausted all retries', [
            'notification_id' => $this->notificationId,
            'error'           => $exception->getMessage(),
            'trace'           => $exception->getTraceAsString(),
        ]);

        // Resolve dependencies manually for the failed callback
        $service = app(NotificationService::class);
        $repository = app(NotificationRepositoryInterface::class);

        $notification = $repository->findById($this->notificationId);

        if ($notification !== null && ! $notification->isProcessed()) {
            $service->markAsFailed($notification);
        }
    }
}
