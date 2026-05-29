<?php


namespace App\Http\Resources;

use App\DTOs\NotificationSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationSummaryData
 */
final class NotificationSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total'      => $this->total,
            'processed'  => $this->processed,
            'failed'     => $this->failed,
            'pending'    => $this->pending,
            'processing' => $this->processing,
        ];
    }
}
