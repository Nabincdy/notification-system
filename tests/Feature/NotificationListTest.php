<?php


use App\Models\Notification;
use Illuminate\Support\Facades\Cache;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GET /api/v1/notifications', function (): void {

    it('returns paginated notifications', function (): void {
        Notification::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'tenant_id', 'user_id', 'type', 'title', 'status'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
                'links',
            ]);
    });

    it('filters by status', function (): void {
        Notification::factory()->pending()->count(3)->create();
        Notification::factory()->processed()->count(5)->create();

        $response = $this->getJson('/api/v1/notifications?status=processed');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect(collect($data)->every(fn ($n) => $n['status'] === 'processed'))->toBeTrue();
    });

    it('filters by type', function (): void {
        Notification::factory()->email()->count(3)->create();
        Notification::factory()->sms()->count(2)->create();

        $response = $this->getJson('/api/v1/notifications?type=email');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect(collect($data)->every(fn ($n) => $n['type'] === 'email'))->toBeTrue();
    });

    it('filters by tenant_id', function (): void {
        Notification::factory()->forTenant(1)->count(3)->create();
        Notification::factory()->forTenant(2)->count(2)->create();

        $response = $this->getJson('/api/v1/notifications?tenant_id=1');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect(collect($data)->every(fn ($n) => $n['tenant_id'] === 1))->toBeTrue();
    });

    it('filters by user_id', function (): void {
        Notification::factory()->forUser(15)->count(3)->create();
        Notification::factory()->forUser(20)->count(2)->create();

        $response = $this->getJson('/api/v1/notifications?user_id=15');

        $response->assertStatus(200);
        $data = $response->json('data');
        expect(collect($data)->every(fn ($n) => $n['user_id'] === 15))->toBeTrue();
    });

    it('returns 422 for invalid status filter', function (): void {
        $response = $this->getJson('/api/v1/notifications?status=bogus');
        $response->assertStatus(422);
    });

    it('respects per_page parameter', function (): void {
        Notification::factory()->count(20)->create();

        $response = $this->getJson('/api/v1/notifications?per_page=5');

        $response->assertStatus(200);
        expect(count($response->json('data')))->toBe(5);
    });
});

describe('GET /api/v1/notifications/summary', function (): void {

    it('returns correct summary counts', function (): void {
        Notification::factory()->pending()->count(10)->create();
        Notification::factory()->processed()->count(80)->create();
        Notification::factory()->failed()->count(10)->create();

        Cache::flush();

        $response = $this->getJson('/api/v1/notifications/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['total', 'processed', 'failed', 'pending', 'processing'],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'total'     => 100,
                    'processed' => 80,
                    'failed'    => 10,
                    'pending'   => 10,
                ],
            ]);
    });

    it('caches summary response', function (): void {
        Cache::spy();

        Notification::factory()->count(3)->create();

        $this->getJson('/api/v1/notifications/summary');

        Cache::shouldHaveReceived('store');
    });
});
