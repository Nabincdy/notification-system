<?php


namespace App\Services\Channels;

use App\Models\Notification;

interface NotificationChannelInterface
{
    public function send(Notification $notification): bool;

    public function supports(string $type): bool;
}
