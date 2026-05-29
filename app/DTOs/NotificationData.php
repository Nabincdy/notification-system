<?php


namespace App\DTOs;

use App\Enums\NotificationType;

final readonly class NotificationData
{
    public function __construct(
        public int              $tenantId,
        public int              $userId,
        public NotificationType $type,
        public string           $title,
        public string           $message,
        public array            $metadata = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: (int) $data['tenant_id'],
            userId:   (int) $data['user_id'],
            type:     NotificationType::from($data['type']),
            title:    $data['title'],
            message:  $data['message'],
            metadata: $data['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'user_id'   => $this->userId,
            'type'      => $this->type->value,
            'title'     => $this->title,
            'message'   => $this->message,
            'metadata'  => $this->metadata,
        ];
    }
}
