<?php


use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {

    Route::prefix('notifications')
        // ->middleware(['throttle:notifications'])
        ->group(function (): void {

            Route::post('/', [NotificationController::class, 'publish'])
                ->name('notifications.publish');

            Route::get('/summary', [NotificationController::class, 'summary'])
                ->name('notifications.summary');

            Route::get('/', [NotificationController::class, 'index'])
                ->name('notifications.index');
        });
});
