<?php


use App\Enums\NotificationStatus;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('POST /api/v1/notifications', function (): void {

    beforeEach(function (): void {
        Queue::fake();
    });

    it('publishes a notification and queues the job', function (): void {
        $payload = [
            'tenant_id' => 1,
            'user_id'   => 15,
            'type'      => 'email',
            'title'     => 'Application Approved',
            'message'   => 'Your application has been approved.',
            'metadata'  => ['application_id' => 1001],
        ];

        $response = $this->postJson('/api/v1/notifications', $payload);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'tenant_id', 'user_id', 'type',
                    'title', 'message', 'metadata', 'status',
                    'attempts', 'processed_at', 'failed_at',
                    'created_at', 'updated_at',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'tenant_id' => 1,
                    'user_id'   => 15,
                    'type'      => 'email',
                    'status'    => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => 1,
            'user_id'   => 15,
            'type'      => 'email',
            'status'    => 'pending',
        ]);

        Queue::assertPushed(ProcessNotificationJob::class);
    });

    it('returns 422 when required fields are missing', function (): void {
        $response = $this->postJson('/api/v1/notifications', []);

        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['errors']);
    });

    it('returns 422 when type is invalid', function (): void {
        $response = $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => 'invalid_type',
            'title'     => 'Test',
            'message'   => 'Test message',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    });

    it('dispatches job to correct queue for each type', function (string $type, string $expectedQueue): void {
        $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => $type,
            'title'     => 'Test',
            'message'   => 'Test',
        ]);

        Queue::assertPushedOn($expectedQueue, ProcessNotificationJob::class);
    })->with([
        ['email', 'notifications-email'],
        ['sms',   'notifications-sms'],
        ['push',  'notifications-push'],
    ]);

    it('accepts optional metadata field', function (): void {
        $response = $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => 'email',
            'title'     => 'Test',
            'message'   => 'Test message',
        ]);

        $response->assertStatus(202);
    });

    it('stores metadata as json', function (): void {
        $metadata = ['key1' => 'value1', 'nested' => ['a' => 'b']];

        $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => 'email',
            'title'     => 'Test',
            'message'   => 'Test',
            'metadata'  => $metadata,
        ]);

        $notification = Notification::latest()->first();
        expect($notification->metadata)->toBe($metadata);
    });
});
