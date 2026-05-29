<?php


use App\DTOs\NotificationData;
use App\Enums\NotificationType;

describe('NotificationData DTO', function (): void {

    it('creates from array', function (): void {
        $dto = NotificationData::fromArray([
            'tenant_id' => 1,
            'user_id'   => 15,
            'type'      => 'email',
            'title'     => 'Test',
            'message'   => 'Message',
            'metadata'  => ['key' => 'value'],
        ]);

        expect($dto->tenantId)->toBe(1)
            ->and($dto->userId)->toBe(15)
            ->and($dto->type)->toBe(NotificationType::Email)
            ->and($dto->title)->toBe('Test')
            ->and($dto->message)->toBe('Message')
            ->and($dto->metadata)->toBe(['key' => 'value']);
    });

    it('defaults metadata to empty array when not provided', function (): void {
        $dto = NotificationData::fromArray([
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => 'email',
            'title'     => 'T',
            'message'   => 'M',
        ]);

        expect($dto->metadata)->toBe([]);
    });

    it('converts to array correctly', function (): void {
        $dto = new NotificationData(
            tenantId: 1,
            userId:   15,
            type:     NotificationType::Email,
            title:    'Title',
            message:  'Message',
            metadata: ['x' => 1],
        );

        expect($dto->toArray())->toBe([
            'tenant_id' => 1,
            'user_id'   => 15,
            'type'      => 'email',
            'title'     => 'Title',
            'message'   => 'Message',
            'metadata'  => ['x' => 1],
        ]);
    });

    it('is readonly', function (): void {
        $dto = NotificationData::fromArray([
            'tenant_id' => 1,
            'user_id'   => 1,
            'type'      => 'email',
            'title'     => 'T',
            'message'   => 'M',
        ]);

        expect(fn () => $dto->tenantId = 99)->toThrow(\Error::class);
    });
});
