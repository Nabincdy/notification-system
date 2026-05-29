<?php


namespace App\DTOs;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;

final readonly class NotificationFilterData
{
    public function __construct(
        public ?NotificationStatus $status    = null,
        public ?NotificationType   $type      = null,
        public ?int                $tenantId  = null,
        public ?int                $userId    = null,
        public int                 $perPage   = 15,
        public int                 $page      = 1,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            status:   isset($data['status'])    ? NotificationStatus::from($data['status'])  : null,
            type:     isset($data['type'])      ? NotificationType::from($data['type'])      : null,
            tenantId: isset($data['tenant_id']) ? (int) $data['tenant_id']                  : null,
            userId:   isset($data['user_id'])   ? (int) $data['user_id']                    : null,
            perPage:  isset($data['per_page'])  ? min((int) $data['per_page'], 100)          : 15,
            page:     isset($data['page'])      ? (int) $data['page']                        : 1,
        );
    }
}
