<?php


namespace App\Services;

use App\DTOs\NotificationData;
use App\DTOs\NotificationFilterData;
use App\DTOs\NotificationSummaryData;
use App\Enums\NotificationStatus;
use App\Exceptions\NotificationAlreadyProcessedException;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Support\CacheKeys;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class NotificationService
{
    private const int SUMMARY_CACHE_TTL = 300; // 5 minutes
    private const int LIST_CACHE_TTL    = 60;  // 1 minute

    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
    ) {}

    public function publish(NotificationData $data): Notification
    {
        return DB::transaction(function () use ($data): Notification {
            $notification = $this->repository->create($data);

            ProcessNotificationJob::dispatch($notification->id)
                ->onQueue($data->type->queueName())
                ->delay(now()->addSeconds(2));

            $this->invalidateSummaryCache();

            Log::info('Notification published', [
                'notification_id' => $notification->id,
                'tenant_id'       => $data->tenantId,
                'user_id'         => $data->userId,
                'type'            => $data->type->value,
            ]);

            return $notification;
        });
    }

    public function paginate(NotificationFilterData $filter): LengthAwarePaginator
    {
        $cacheKey = CacheKeys::notificationList($filter);

        return Cache::store('redis')->remember(
            $cacheKey,
            self::LIST_CACHE_TTL,
            fn () => $this->repository->paginate($filter),
        );
    }

    public function getSummary(): NotificationSummaryData
    {
        return Cache::store('redis')->remember(
            CacheKeys::notificationSummary(),
            self::SUMMARY_CACHE_TTL,
            fn () => $this->repository->getSummary(),
        );
    }

    public function markAsProcessing(Notification $notification): void
    {
        $this->repository->updateStatus(
            $notification,
            NotificationStatus::Processing,
            ['processed_at' => null],
        );
    }

    public function markAsProcessed(Notification $notification): void
    {
        $this->repository->updateStatus(
            $notification,
            NotificationStatus::Processed,
            ['processed_at' => now()],
        );

        $this->invalidateSummaryCache();
    }

    public function markAsFailed(Notification $notification): void
    {
        $this->repository->updateStatus(
            $notification,
            NotificationStatus::Failed,
            ['failed_at' => now()],
        );

        $this->invalidateSummaryCache();

        Log::error('Notification permanently failed', [
            'notification_id' => $notification->id,
            'attempts'        => $notification->attempts,
        ]);
    }

    public function incrementAttempts(Notification $notification): void
    {
        $this->repository->incrementAttempts($notification);
        $notification->refresh();
    }

    public function isAlreadyProcessed(int $id): bool
    {
        return $this->repository->existsWithStatus($id, NotificationStatus::Processed);
    }

    private function invalidateSummaryCache(): void
    {
        Cache::store('redis')->forget(CacheKeys::notificationSummary());
    }
}
