<?php


namespace App\Services\Channels;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

final class SmsChannel implements NotificationChannelInterface
{
    public function send(Notification $notification): bool
    {
        Log::info('Sending SMS notification', [
            'notification_id' => $notification->id,
            'tenant_id'       => $notification->tenant_id,
            'user_id'         => $notification->user_id,
            'title'           => $notification->title,
            'message'         => $notification->message,
        ]);

        // Future: integrate Twilio / Nexmo / AWS SNS here
        return true;
    }

    public function supports(string $type): bool
    {
        return $type === NotificationType::SMS->value;
    }
}
