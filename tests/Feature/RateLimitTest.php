<?php


use App\Jobs\ProcessNotificationJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Rate Limiting', function (): void {

    beforeEach(function (): void {
        Queue::fake();
        RateLimiter::clear('notifications:user:999');
    });

    it('allows 10 requests per user per hour', function (): void {
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/notifications', [
                'tenant_id' => 1,
                'user_id'   => 999,
                'type'      => 'email',
                'title'     => "Notification {$i}",
                'message'   => 'Test message',
            ]);

            $response->assertStatus(202);
        }
    });

    it('blocks the 11th request per user per hour', function (): void {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/notifications', [
                'tenant_id' => 1,
                'user_id'   => 999,
                'type'      => 'email',
                'title'     => "Notification {$i}",
                'message'   => 'Test message',
            ]);
        }

        $response = $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 999,
            'type'      => 'email',
            'title'     => 'Over limit',
            'message'   => 'This should be blocked',
        ]);

        $response->assertStatus(429)
            ->assertJson([
                'success' => false,
            ]);
    });

    it('rate limits are per user not global', function (): void {
        // Fill rate limit for user 999
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/notifications', [
                'tenant_id' => 1,
                'user_id'   => 999,
                'type'      => 'email',
                'title'     => "Msg {$i}",
                'message'   => 'Test',
            ]);
        }

        // User 1000 should not be rate limited
        $response = $this->postJson('/api/v1/notifications', [
            'tenant_id' => 1,
            'user_id'   => 1000,
            'type'      => 'email',
            'title'     => 'Different user',
            'message'   => 'Should succeed',
        ]);

        $response->assertStatus(202);
    });
});
