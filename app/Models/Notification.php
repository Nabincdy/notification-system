<?php


namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

/**
 * @property int                 $id
 * @property int                 $tenant_id
 * @property int                 $user_id
 * @property NotificationType    $type
 * @property string              $title
 * @property string              $message
 * @property array|null          $metadata
 * @property NotificationStatus  $status
 * @property int                 $attempts
 * @property \Carbon\Carbon|null $processed_at
 * @property \Carbon\Carbon|null $failed_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $updated_at
 */
final class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'title',
        'message',
        'metadata',
        'status',
        'attempts',
        'processed_at',
        'failed_at',
    ];

    protected $casts = [
        'tenant_id'    => 'integer',
        'user_id'      => 'integer',
        'type'         => NotificationType::class,
        'status'       => NotificationStatus::class,
        'metadata'     => 'array',
        'attempts'     => 'integer',
        'processed_at' => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOfStatus(Builder $query, NotificationStatus $status): Builder
    {
        return $query->where('status', $status->value);
    }

    public function scopeOfType(Builder $query, NotificationType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function isPending(): bool
    {
        return $this->status === NotificationStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === NotificationStatus::Processing;
    }

    public function isProcessed(): bool
    {
        return $this->status === NotificationStatus::Processed;
    }

    public function isFailed(): bool
    {
        return $this->status === NotificationStatus::Failed;
    }
}
