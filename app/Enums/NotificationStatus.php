<?php


namespace App\Enums;

enum NotificationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Processed = 'processed';
    case Failed = 'failed';

    public function label(): string
    {
        return match($this) {
            self::Pending    => 'Pending',
            self::Processing => 'Processing',
            self::Processed  => 'Processed',
            self::Failed     => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return match($this) {
            self::Processed, self::Failed => true,
            default                       => false,
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::Pending    => in_array($next, [self::Processing, self::Failed]),
            self::Processing => in_array($next, [self::Processed, self::Failed, self::Pending]),
            self::Processed  => false,
            self::Failed     => false,
        };
    }
}
