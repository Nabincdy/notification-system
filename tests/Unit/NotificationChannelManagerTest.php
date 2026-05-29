<?php


use App\Enums\NotificationType;
use App\Exceptions\UnsupportedNotificationChannelException;
use App\Managers\NotificationChannelManager;
use App\Models\Notification;
use App\Services\Channels\EmailChannel;
use App\Services\Channels\NotificationChannelInterface;
use App\Services\Channels\PushChannel;
use App\Services\Channels\SmsChannel;

describe('NotificationChannelManager', function (): void {

    it('resolves email channel', function (): void {
        $manager = new NotificationChannelManager();
        $manager->register(new EmailChannel());

        $notification = new Notification();
        $notification->type = NotificationType::Email;

        $channel = $manager->resolveFor($notification);
        expect($channel)->toBeInstanceOf(EmailChannel::class);
    });

    it('resolves sms channel', function (): void {
        $manager = new NotificationChannelManager();
        $manager->register(new SmsChannel());

        $notification = new Notification();
        $notification->type = NotificationType::SMS;

        $channel = $manager->resolveFor($notification);
        expect($channel)->toBeInstanceOf(SmsChannel::class);
    });

    it('resolves push channel', function (): void {
        $manager = new NotificationChannelManager();
        $manager->register(new PushChannel());

        $notification = new Notification();
        $notification->type = NotificationType::Push;

        $channel = $manager->resolveFor($notification);
        expect($channel)->toBeInstanceOf(PushChannel::class);
    });

    it('throws when no channel supports the type', function (): void {
        $manager = new NotificationChannelManager();

        $notification = new Notification();
        $notification->type = NotificationType::Email;

        expect(fn () => $manager->resolveFor($notification))
            ->toThrow(UnsupportedNotificationChannelException::class);
    });

    it('resolves first matching channel when multiple registered', function (): void {
        $primary = Mockery::mock(NotificationChannelInterface::class);
        $primary->shouldReceive('supports')->with('email')->andReturn(true);

        $secondary = Mockery::mock(NotificationChannelInterface::class);
        $secondary->shouldNotReceive('supports');

        $manager = new NotificationChannelManager();
        $manager->register($primary);
        $manager->register($secondary);

        $notification = new Notification();
        $notification->type = NotificationType::Email;

        $resolved = $manager->resolveFor($notification);
        expect($resolved)->toBe($primary);
    });
});
