<?php


namespace App\Managers;

use App\Exceptions\UnsupportedNotificationChannelException;
use App\Models\Notification;
use App\Services\Channels\NotificationChannelInterface;

final class NotificationChannelManager
{
    /** @var list<NotificationChannelInterface> */
    private array $channels = [];

    public function register(NotificationChannelInterface $channel): void
    {
        $this->channels[] = $channel;
    }

    /**
     * @throws UnsupportedNotificationChannelException
     */
    public function resolveFor(Notification $notification): NotificationChannelInterface
    {
        foreach ($this->channels as $channel) {
            if ($channel->supports($notification->type->value)) {
                return $channel;
            }
        }

        throw new UnsupportedNotificationChannelException(
            "No channel found for type: {$notification->type->value}"
        );
    }
}
