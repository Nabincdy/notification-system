<?php


namespace App\Repositories\Eloquent;

use App\DTOs\NotificationData;
use App\DTOs\NotificationFilterData;
use App\DTOs\NotificationSummaryData;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class EloquentNotificationRepository implements NotificationRepositoryInterface
{
    public function __construct(
        private readonly Notification $model,
    ) {}

    public function create(NotificationData $data): Notification
    {
        return $this->model->newQuery()->create([
            'tenant_id' => $data->tenantId,
            'user_id'   => $data->userId,
            'type'      => $data->type->value,
            'title'     => $data->title,
            'message'   => $data->message,
            'metadata'  => $data->metadata,
            'status'    => NotificationStatus::Pending->value,
            'attempts'  => 0,
        ]);
    }

    public function findById(int $id): ?Notification
    {
        return $this->model->newQuery()->find($id);
    }

    public function findByIdOrFail(int $id): Notification
    {
        return $this->model->newQuery()->findOrFail($id);
    }

    public function updateStatus(
        Notification       $notification,
        NotificationStatus $status,
        ?array             $extra = null,
    ): bool {
        $attributes = array_merge(['status' => $status->value], $extra ?? []);

        return (bool) $notification->update($attributes);
    }

    public function incrementAttempts(Notification $notification): bool
    {
        return (bool) $notification->increment('attempts');
    }

    public function paginate(NotificationFilterData $filter): LengthAwarePaginator
    {
        $query = $this->model->newQuery()
            ->orderByDesc('created_at');

        if ($filter->status !== null) {
            $query->ofStatus($filter->status);
        }

        if ($filter->type !== null) {
            $query->ofType($filter->type);
        }

        if ($filter->tenantId !== null) {
            $query->forTenant($filter->tenantId);
        }

        if ($filter->userId !== null) {
            $query->forUser($filter->userId);
        }

        return $query->paginate(
            perPage: $filter->perPage,
            page:    $filter->page,
        );
    }

    public function getSummary(): NotificationSummaryData
    {
        $results = $this->model->newQuery()
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $total = array_sum($results);

        return NotificationSummaryData::fromArray([
            'total'      => $total,
            'processed'  => $results[NotificationStatus::Processed->value]  ?? 0,
            'failed'     => $results[NotificationStatus::Failed->value]      ?? 0,
            'pending'    => $results[NotificationStatus::Pending->value]     ?? 0,
            'processing' => $results[NotificationStatus::Processing->value]  ?? 0,
        ]);
    }

    public function existsWithStatus(int $id, NotificationStatus $status): bool
    {
        return $this->model->newQuery()
            ->where('id', $id)
            ->where('status', $status->value)
            ->exists();
    }
}
