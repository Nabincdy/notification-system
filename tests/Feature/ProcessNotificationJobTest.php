<?php


use App\Enums\NotificationStatus;
use App\Exceptions\UnsupportedNotificationChannelException;
use App\Jobs\ProcessNotificationJob;
use App\Managers\NotificationChannelManager;
use App\Models\Notification;
use App\Services\Channels\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('ProcessNotificationJob', function (): void {

    it('processes a pending notification successfully', function (): void {
        $notification = Notification::factory()->pending()->email()->create();

        $mockChannel = Mockery::mock(NotificationChannelInterface::class);
        $mockChannel->shouldReceive('supports')->with('email')->andReturn(true);
        $mockChannel->shouldReceive('send')->once()->andReturn(true);

        $manager = new NotificationChannelManager();
        $manager->register($mockChannel);
        $this->app->instance(NotificationChannelManager::class, $manager);

        (new ProcessNotificationJob($notification->id))->handle(
            app(\App\Services\NotificationService::class),
            $manager,
            app(\App\Repositories\Contracts\NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Processed);
        expect($notification->processed_at)->not->toBeNull();
    });

    it('skips already processed notifications (idempotency)', function (): void {
        $notification = Notification::factory()->processed()->create();

        $manager = Mockery::mock(NotificationChannelManager::class);
        $manager->shouldNotReceive('resolveFor');

        (new ProcessNotificationJob($notification->id))->handle(
            app(\App\Services\NotificationService::class),
            $manager,
            app(\App\Repositories\Contracts\NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Processed);
    });

    it('returns early when notification does not exist', function (): void {
        Log::shouldReceive('warning')->once()->with('ProcessNotificationJob: notification not found', Mockery::any());

        $manager = Mockery::mock(NotificationChannelManager::class);
        $manager->shouldNotReceive('resolveFor');

        (new ProcessNotificationJob(999999))->handle(
            app(\App\Services\NotificationService::class),
            $manager,
            app(\App\Repositories\Contracts\NotificationRepositoryInterface::class),
        );
    });

    it('increments attempts before processing', function (): void {
        $notification = Notification::factory()->pending()->email()->create(['attempts' => 0]);

        $mockChannel = Mockery::mock(NotificationChannelInterface::class);
        $mockChannel->shouldReceive('supports')->andReturn(true);
        $mockChannel->shouldReceive('send')->andReturn(true);

        $manager = new NotificationChannelManager();
        $manager->register($mockChannel);
        $this->app->instance(NotificationChannelManager::class, $manager);

        (new ProcessNotificationJob($notification->id))->handle(
            app(\App\Services\NotificationService::class),
            $manager,
            app(\App\Repositories\Contracts\NotificationRepositoryInterface::class),
        );

        $notification->refresh();
        expect($notification->attempts)->toBe(1);
    });

    it('marks as failed permanently on unsupported channel', function (): void {
        $notification = Notification::factory()->pending()->create(['type' => 'email']);

        $manager = Mockery::mock(NotificationChannelManager::class);
        $manager->shouldReceive('resolveFor')->andThrow(
            new UnsupportedNotificationChannelException('No channel for email')
        );

        $job = new ProcessNotificationJob($notification->id);

        expect(fn () => $job->handle(
            app(\App\Services\NotificationService::class),
            $manager,
            app(\App\Repositories\Contracts\NotificationRepositoryInterface::class),
        ))->toThrow(UnsupportedNotificationChannelException::class);

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Failed);
    });

    it('has unique job constraint', function (): void {
        $job = new ProcessNotificationJob(42);
        expect($job->uniqueId())->toBe('process-notification:42');
    });

    it('has exponential backoff configured', function (): void {
        $job = new ProcessNotificationJob(1);
        expect($job->backoff())->toBe([10, 30, 90, 270, 810]);
    });

    it('dispatches on correct queue via Queue fake', function (): void {
        Queue::fake();

        Notification::factory()->email()->create(['id' => 1]);

        ProcessNotificationJob::dispatch(1)->onQueue('notifications-email');

        Queue::assertPushedOn('notifications-email', ProcessNotificationJob::class);
    });

    it('calls failed() and marks as failed after exhausting retries', function (): void {
        $notification = Notification::factory()->processing()->create();

        $job = new ProcessNotificationJob($notification->id);
        $job->failed(new \RuntimeException('Max retries exceeded'));

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Failed);
        expect($notification->failed_at)->not->toBeNull();
    });
});
