<?php


namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
final class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'tenant_id'    => $this->tenant_id,
            'user_id'      => $this->user_id,
            'type'         => $this->type->value,
            'title'        => $this->title,
            'message'      => $this->message,
            'metadata'     => $this->metadata,
            'status'       => $this->status->value,
            'attempts'     => $this->attempts,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'failed_at'    => $this->failed_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
        ];
    }
}
