<?php


namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'tenant_id'    => $this->faker->numberBetween(1, 100),
            'user_id'      => $this->faker->numberBetween(1, 1000),
            'type'         => $this->faker->randomElement(NotificationType::cases())->value,
            'title'        => $this->faker->sentence(4),
            'message'      => $this->faker->paragraph(),
            'metadata'     => ['key' => $this->faker->word()],
            'status'       => NotificationStatus::Pending->value,
            'attempts'     => 0,
            'processed_at' => null,
            'failed_at'    => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => NotificationStatus::Pending->value]);
    }

    public function processing(): static
    {
        return $this->state(['status' => NotificationStatus::Processing->value]);
    }

    public function processed(): static
    {
        return $this->state([
            'status'       => NotificationStatus::Processed->value,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status'     => NotificationStatus::Failed->value,
            'failed_at'  => now(),
            'attempts'   => 5,
        ]);
    }

    public function email(): static
    {
        return $this->state(['type' => NotificationType::Email->value]);
    }

    public function sms(): static
    {
        return $this->state(['type' => NotificationType::SMS->value]);
    }

    public function forTenant(int $tenantId): static
    {
        return $this->state(['tenant_id' => $tenantId]);
    }

    public function forUser(int $userId): static
    {
        return $this->state(['user_id' => $userId]);
    }
}
