<?php

use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : redirect()->route('login'));

require __DIR__.'/auth.php';

Route::middleware(['auth', 'module.access'])->group(function () {
    // Hub, profile and notifications (always open).
    Route::get('/dashboard', [ModuleController::class, 'index'])->name('dashboard');
    Route::get('/modules/{module}', [ModuleController::class, 'enter'])->name('modules.enter');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/signature', [ProfileController::class, 'signature'])->name('profile.signature');
    Route::middleware('throttle:integration-tests')->group(function () {
        Route::post('/profile/telegram/connect', [TelegramConnectController::class, 'connect'])->name('profile.telegram.connect');
        Route::post('/profile/telegram/confirm', [TelegramConnectController::class, 'confirm'])->name('profile.telegram.confirm');
        Route::post('/profile/telegram/disconnect', [TelegramConnectController::class, 'disconnect'])->name('profile.telegram.disconnect');
        Route::post('/profile/telegram/test', [TelegramConnectController::class, 'test'])->name('profile.telegram.test');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    // Administration (admin only).
    Route::middleware('can:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('/users/{user}/pin', [UserController::class, 'pin'])->name('users.pin');
        Route::post('/users/{user}/signature', [UserController::class, 'signature'])->name('users.signature');
    });
});
