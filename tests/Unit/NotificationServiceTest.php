<?php


use App\DTOs\NotificationData;
use App\DTOs\NotificationFilterData;
use App\DTOs\NotificationSummaryData;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('NotificationService', function (): void {

    beforeEach(function (): void {
        Queue::fake();
        Cache::flush();
    });

    it('creates and queues a notification', function (): void {
        $data = new NotificationData(
            tenantId: 1,
            userId:   15,
            type:     NotificationType::Email,
            title:    'Test',
            message:  'Test message',
        );

        $service = app(NotificationService::class);
        $notification = $service->publish($data);

        expect($notification)->toBeInstanceOf(Notification::class)
            ->and($notification->status)->toBe(NotificationStatus::Pending);

        Queue::assertPushed(ProcessNotificationJob::class);
    });

    it('marks notification as processing', function (): void {
        $notification = Notification::factory()->pending()->create();

        $service = app(NotificationService::class);
        $service->markAsProcessing($notification);

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Processing);
    });

    it('marks notification as processed and sets timestamp', function (): void {
        $notification = Notification::factory()->processing()->create();

        $service = app(NotificationService::class);
        $service->markAsProcessed($notification);

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Processed)
            ->and($notification->processed_at)->not->toBeNull();
    });

    it('marks notification as failed and sets timestamp', function (): void {
        $notification = Notification::factory()->processing()->create();

        $service = app(NotificationService::class);
        $service->markAsFailed($notification);

        $notification->refresh();
        expect($notification->status)->toBe(NotificationStatus::Failed)
            ->and($notification->failed_at)->not->toBeNull();
    });

    it('invalidates summary cache on status update', function (): void {
        Cache::store('redis')->put('notifications:summary', 'stale', 300);

        $notification = Notification::factory()->processing()->create();
        $service = app(NotificationService::class);
        $service->markAsProcessed($notification);

        expect(Cache::store('redis')->has('notifications:summary'))->toBeFalse();
    });

    it('returns cached summary on second call', function (): void {
        Notification::factory()->processed()->count(5)->create();
        Notification::factory()->failed()->count(2)->create();

        $service = app(NotificationService::class);
        $summary1 = $service->getSummary();
        $summary2 = $service->getSummary();

        expect($summary1->total)->toBe($summary2->total);
    });

    it('detects already processed notification', function (): void {
        $notification = Notification::factory()->processed()->create();

        $service = app(NotificationService::class);
        expect($service->isAlreadyProcessed($notification->id))->toBeTrue();
    });

    it('increments attempts counter', function (): void {
        $notification = Notification::factory()->create(['attempts' => 2]);

        $service = app(NotificationService::class);
        $service->incrementAttempts($notification);

        $notification->refresh();
        expect($notification->attempts)->toBe(3);
    });
});
