<?php


namespace App\Providers;

use App\Managers\NotificationChannelManager;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryInterface;
use App\Repositories\Eloquent\EloquentNotificationRepository;
use App\Services\Channels\EmailChannel;
use App\Services\Channels\PushChannel;
use App\Services\Channels\SmsChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            NotificationRepositoryInterface::class,
            fn () => new EloquentNotificationRepository(new Notification()),
        );

        $this->app->singleton(NotificationChannelManager::class, function (): NotificationChannelManager {
            $manager = new NotificationChannelManager();
            $manager->register(new EmailChannel());
            $manager->register(new SmsChannel());
            $manager->register(new PushChannel());
            return $manager;
        });
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('notifications', function (Request $request): Limit {
            $userId = (int) $request->input('user_id', 0);

            if ($userId <= 0) {
                return Limit::perHour(10)->by($request->ip());
            }

            return Limit::perHour(10)
                ->by("notifications:user:{$userId}")
                ->response(function () {
                    return response()->json([
                        'success' => false,
                        'message' => 'Rate limit exceeded. Maximum 10 notifications per user per hour.',
                        'errors'  => [],
                    ], 429);
                });
        });
    }
}
