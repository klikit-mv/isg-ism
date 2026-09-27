<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnnualFeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassFeeController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentLinkController;
use App\Http\Controllers\ParentRegistrationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVerificationController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SelfController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StudentController;
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

    Route::get('/photos/{fileId}', [PhotoController::class, 'show'])->name('photos.show');

    // Scout operations: students, promotion, registrations, groups.
    Route::get('/students/promote', [PromotionController::class, 'index'])->name('promotion.index');
    Route::post('/students/promote', [PromotionController::class, 'store'])->name('promotion.store');
    Route::resource('students', StudentController::class);
    Route::get('/students/{student}/certificates', [StudentController::class, 'certificates'])->name('students.certificates');
    Route::get('/students/{student}/badge-requests', [StudentController::class, 'badgeRequests'])->name('students.badge-requests');
    Route::get('/students/{student}/leadership', [StudentController::class, 'leadership'])->name('students.leadership');
    Route::post('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
    Route::post('/students/{student}/verify', [StudentController::class, 'verify'])->name('students.verify');
    Route::post('/students/{student}/reject', [StudentController::class, 'reject'])->name('students.reject');

    Route::get('/parent-registrations', [ParentRegistrationController::class, 'index'])->name('parent-registrations.index');
    Route::post('/parent-registrations/{user}/verify', [ParentRegistrationController::class, 'verify'])->name('parent-registrations.verify');
    Route::post('/parent-registrations/{user}/reject', [ParentRegistrationController::class, 'reject'])->name('parent-registrations.reject');

    Route::get('/groups', [GroupController::class, 'index'])->name('groups.index');
    Route::post('/groups', [GroupController::class, 'store'])->name('groups.store');
    Route::get('/groups/{group}', [GroupController::class, 'show'])->name('groups.show');
    Route::put('/groups/{group}', [GroupController::class, 'update'])->name('groups.update');
    Route::delete('/groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy');
    Route::put('/groups/{group}/membership', [GroupController::class, 'membership'])->name('groups.membership');

    // Activities and attendance.
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::get('/activities/{activity}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
    Route::put('/activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::delete('/activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/{activity}/mark', [AttendanceController::class, 'mark'])->name('attendance.mark');
    Route::get('/rover-attendance', [AttendanceController::class, 'roverIndex'])->name('rover-attendance.index');
    Route::get('/rover-attendance/{activity}/mark', [AttendanceController::class, 'roverMark'])->name('rover-attendance.mark');

    // Fees and payments.
    Route::get('/class-fees', [ClassFeeController::class, 'index'])->name('class-fees.index');
    Route::get('/annual-fees', [AnnualFeeController::class, 'index'])->name('annual-fees.index');
    Route::get('/annual-fees/years', [AnnualFeeController::class, 'years'])->name('annual-fees.years');
    Route::post('/annual-fees/years', [AnnualFeeController::class, 'storeYear'])->name('annual-fees.years.store');
    Route::post('/annual-fees/years/{year}/status', [AnnualFeeController::class, 'yearStatus'])->name('annual-fees.years.status');
    Route::get('/annual-fees/years/{year}/generate', [AnnualFeeController::class, 'generateForm'])->name('annual-fees.generate');
    Route::post('/annual-fees/years/{year}/generate', [AnnualFeeController::class, 'generate'])->name('annual-fees.generate.store');
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
    Route::get('/payments/{payment}/proof', [PaymentController::class, 'proof'])->name('payments.proof');
    Route::get('/payment-verification', [PaymentVerificationController::class, 'index'])->name('payment-verification.index');
    Route::post('/payments/{payment}/approve', [PaymentVerificationController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{payment}/reject', [PaymentVerificationController::class, 'reject'])->name('payments.reject');

    // Shop.
    Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
    Route::post('/shop', [ShopController::class, 'store'])->name('shop.store');
    Route::put('/shop/{item}', [ShopController::class, 'update'])->name('shop.update');
    Route::delete('/shop/{item}', [ShopController::class, 'destroy'])->name('shop.destroy');
    Route::post('/shop/{item}/buy', [ShopController::class, 'buy'])->name('shop.buy');
    Route::get('/purchases', [PurchaseController::class, 'index'])->name('purchases.index');
    Route::post('/purchases/{purchase}/ready', [PurchaseController::class, 'ready'])->name('purchases.ready');
    Route::post('/purchases/{purchase}/deliver', [PurchaseController::class, 'deliver'])->name('purchases.deliver');
    Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');

    // Family (parents) and My record (scouts).
    Route::get('/family', [FamilyController::class, 'index'])->name('family.index');
    Route::get('/family/attendance', [FamilyController::class, 'attendance'])->name('family.attendance');
    Route::get('/family/students/{student}', [FamilyController::class, 'show'])->name('family.show');
    Route::get('/family/students/{student}/certificates', [FamilyController::class, 'certificates'])->name('family.student.certificates');
    Route::get('/family/students/{student}/badge-requests', [FamilyController::class, 'badgeRequests'])->name('family.student.badge-requests');
    Route::get('/family/students/{student}/leadership', [FamilyController::class, 'leadership'])->name('family.student.leadership');

    Route::get('/me', [SelfController::class, 'show'])->name('self.show');
    Route::get('/me/certificates', [SelfController::class, 'certificates'])->name('self.certificates');
    Route::get('/me/badge-requests', [SelfController::class, 'badgeRequests'])->name('self.badge-requests');
    Route::get('/me/leadership', [SelfController::class, 'leadership'])->name('self.leadership');
    Route::get('/me/attendance', [SelfController::class, 'attendance'])->name('self.attendance');
    Route::get('/me/fees', [SelfController::class, 'fees'])->name('self.fees');

    // Administration (admin only).
    Route::middleware('can:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('/users/{user}/pin', [UserController::class, 'pin'])->name('users.pin');
        Route::post('/users/{user}/signature', [UserController::class, 'signature'])->name('users.signature');
        Route::get('/parent-links', [ParentLinkController::class, 'index'])->name('parent-links.index');
        Route::post('/parent-links', [ParentLinkController::class, 'store'])->name('parent-links.store');
        Route::post('/parent-links/{parentLink}', [ParentLinkController::class, 'update'])->name('parent-links.update');
    });
});
