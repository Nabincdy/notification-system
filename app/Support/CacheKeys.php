<?php


namespace App\Support;

use App\DTOs\NotificationFilterData;

final class CacheKeys
{
    private const string PREFIX = 'notifications';

    public static function notificationSummary(): string
    {
        return self::PREFIX . ':summary';
    }

    public static function notificationList(NotificationFilterData $filter): string
    {
        $parts = [
            self::PREFIX,
            'list',
            'status:'   . ($filter->status?->value   ?? 'all'),
            'type:'     . ($filter->type?->value      ?? 'all'),
            'tenant:'   . ($filter->tenantId          ?? 'all'),
            'user:'     . ($filter->userId            ?? 'all'),
            'page:'     . $filter->page,
            'per_page:' . $filter->perPage,
        ];

        return implode(':', $parts);
    }

    public static function rateLimitKey(int $userId): string
    {
        return self::PREFIX . ":rate_limit:user:{$userId}";
    }
}
