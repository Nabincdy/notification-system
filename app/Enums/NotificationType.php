<?php


namespace App\Enums;

enum NotificationType: string
{
    case Email = 'email';
    case SMS   = 'sms';
    case Push  = 'push';

    public function label(): string
    {
        return match($this) {
            self::Email => 'Email',
            self::SMS   => 'SMS',
            self::Push  => 'Push Notification',
        };
    }

    public function queueName(): string
    {
        return match($this) {
            self::Email => 'notifications-email',
            self::SMS   => 'notifications-sms',
            self::Push  => 'notifications-push',
        };
    }
}
