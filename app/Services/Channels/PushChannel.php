<?php


namespace App\Services\Channels;

use App\Enums\NotificationType;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

final class PushChannel implements NotificationChannelInterface
{
    public function send(Notification $notification): bool
    {
        Log::info('Sending push notification', [
            'notification_id' => $notification->id,
            'tenant_id'       => $notification->tenant_id,
            'user_id'         => $notification->user_id,
            'title'           => $notification->title,
            'message'         => $notification->message,
        ]);

        // Future: integrate Firebase FCM / APNs here
        return true;
    }

    public function supports(string $type): bool
    {
        return $type === NotificationType::Push->value;
    }
}
