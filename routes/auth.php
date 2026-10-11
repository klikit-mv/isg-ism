<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PinResetController;
use App\Http\Controllers\Auth\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-pin', [PinResetController::class, 'request'])->name('pin.forgot');
    Route::post('forgot-pin', [PinResetController::class, 'send'])->middleware('throttle:pin-reset')->name('pin.send');
    Route::get('reset-pin/{token}', [PinResetController::class, 'edit'])->name('pin.reset');
    Route::post('reset-pin/{token}', [PinResetController::class, 'update'])->middleware('throttle:pin-reset')->name('pin.update');

    Route::get('register', [RegistrationController::class, 'create'])->name('register');
    Route::post('register', [RegistrationController::class, 'store'])->middleware('throttle:registration');
    Route::get('register/parent', [RegistrationController::class, 'createParent'])->name('register.parent');
    Route::post('register/parent', [RegistrationController::class, 'storeParent'])->middleware('throttle:registration');
});

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
