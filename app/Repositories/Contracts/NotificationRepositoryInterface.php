<?php


namespace App\Repositories\Contracts;

use App\DTOs\NotificationData;
use App\DTOs\NotificationFilterData;
use App\DTOs\NotificationSummaryData;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificationRepositoryInterface
{
    public function create(NotificationData $data): Notification;

    public function findById(int $id): ?Notification;

    public function findByIdOrFail(int $id): Notification;

    public function updateStatus(
        Notification       $notification,
        NotificationStatus $status,
        ?array             $extra = null,
    ): bool;

    public function incrementAttempts(Notification $notification): bool;

    public function paginate(NotificationFilterData $filter): LengthAwarePaginator;

    public function getSummary(): NotificationSummaryData;

    public function existsWithStatus(int $id, NotificationStatus $status): bool;
}
