<?php


use App\Enums\NotificationStatus;
use App\Enums\NotificationType;

describe('NotificationStatus', function (): void {

    it('has correct values', function (): void {
        expect(NotificationStatus::Pending->value)->toBe('pending')
            ->and(NotificationStatus::Processing->value)->toBe('processing')
            ->and(NotificationStatus::Processed->value)->toBe('processed')
            ->and(NotificationStatus::Failed->value)->toBe('failed');
    });

    it('identifies terminal states', function (): void {
        expect(NotificationStatus::Processed->isTerminal())->toBeTrue()
            ->and(NotificationStatus::Failed->isTerminal())->toBeTrue()
            ->and(NotificationStatus::Pending->isTerminal())->toBeFalse()
            ->and(NotificationStatus::Processing->isTerminal())->toBeFalse();
    });

    it('validates valid transitions', function (): void {
        expect(NotificationStatus::Pending->canTransitionTo(NotificationStatus::Processing))->toBeTrue()
            ->and(NotificationStatus::Processing->canTransitionTo(NotificationStatus::Processed))->toBeTrue()
            ->and(NotificationStatus::Processing->canTransitionTo(NotificationStatus::Failed))->toBeTrue()
            ->and(NotificationStatus::Processed->canTransitionTo(NotificationStatus::Failed))->toBeFalse()
            ->and(NotificationStatus::Failed->canTransitionTo(NotificationStatus::Pending))->toBeFalse();
    });
});

describe('NotificationType', function (): void {

    it('has correct queue names', function (): void {
        expect(NotificationType::Email->queueName())->toBe('notifications-email')
            ->and(NotificationType::SMS->queueName())->toBe('notifications-sms')
            ->and(NotificationType::Push->queueName())->toBe('notifications-push');
    });

    it('has correct labels', function (): void {
        expect(NotificationType::Email->label())->toBe('Email')
            ->and(NotificationType::SMS->label())->toBe('SMS')
            ->and(NotificationType::Push->label())->toBe('Push Notification');
    });
});
