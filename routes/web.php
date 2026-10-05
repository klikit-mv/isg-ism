<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AnnualFeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BadgeRequestController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\ClassFeeController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventItemController;
use App\Http\Controllers\EventRegistrationController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\GoogleConnectController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LeadershipController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ParentLinkController;
use App\Http\Controllers\ParentRegistrationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentVerificationController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SelfController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\TelegramConnectController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Home page and event details are public; registering needs an account.
Route::get('/', [PublicEventController::class, 'home'])->name('home');
Route::get('/upcoming-events/{event}', [PublicEventController::class, 'show'])->name('public.events.show');

require __DIR__.'/auth.php';

// Website logo (shown on the sign-in page, so no sign-in needed).
Route::get('/branding/logo', [MediaController::class, 'logo'])->name('branding.logo');

// Public certificate verification (no sign-in).
Route::middleware('throttle:certificate-verify')->group(function () {
    Route::get('/certificates/verify', [CertificateVerificationController::class, 'verify'])->name('certificates.verify');
    Route::get('/certificates/verify/view', [CertificateVerificationController::class, 'view'])->name('certificates.verify.view');
    Route::get('/certificates/verify/download', [CertificateVerificationController::class, 'download'])->name('certificates.verify.download');
});

Route::middleware(['auth', 'module.access'])->group(function () {
    // Hub, profile and notifications (always open).
    Route::get('/dashboard', [ModuleController::class, 'index'])->name('dashboard');
    Route::get('/modules/{module}', [ModuleController::class, 'enter'])->name('modules.enter');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/signature', [ProfileController::class, 'signature'])->name('profile.signature');
    Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::post('/profile/telegram/confirm', [TelegramConnectController::class, 'confirm'])->middleware('throttle:60,1')->name('profile.telegram.confirm');
    Route::middleware('throttle:integration-tests')->group(function () {
        Route::post('/profile/telegram/connect', [TelegramConnectController::class, 'connect'])->name('profile.telegram.connect');
        Route::post('/profile/telegram/disconnect', [TelegramConnectController::class, 'disconnect'])->name('profile.telegram.disconnect');
        Route::post('/profile/telegram/test', [TelegramConnectController::class, 'test'])->name('profile.telegram.test');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/photos/{fileId}', [PhotoController::class, 'show'])->name('photos.show');
    Route::get('/media/{path}', [MediaController::class, 'show'])->where('path', '.*')->name('media.show');

    // Scout operations: students, promotion, registrations, groups.
    Route::get('/students/promote', [PromotionController::class, 'index'])->name('promotion.index');
    Route::post('/students/promote', [PromotionController::class, 'store'])->name('promotion.store');
    Route::get('/students/import', [StudentImportController::class, 'index'])->name('students.import');
    Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
    Route::post('/students/import/preview', [StudentImportController::class, 'preview'])->name('students.import.preview');
    Route::post('/students/import/confirm', [StudentImportController::class, 'confirm'])->name('students.import.confirm');
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

    // Certificates, badges, requests, templates and leadership.
    Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::get('/certificates/create', [CertificateController::class, 'create'])->name('certificates.create');
    Route::post('/certificates', [CertificateController::class, 'store'])->name('certificates.store');
    Route::get('/certificates/bulk-create', [CertificateController::class, 'bulkCreate'])->name('certificates.bulk-create');
    Route::post('/certificates/bulk-create', [CertificateController::class, 'bulkStore'])->name('certificates.bulk-store');
    Route::post('/certificates/bulk-download', [CertificateController::class, 'bulkDownload'])->name('certificates.bulk-download');
    Route::post('/certificates/activities/{activity}/issue', [CertificateController::class, 'issueActivity'])->name('certificates.activities.issue');
    Route::get('/certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');
    Route::get('/certificates/{certificate}/preview', [CertificateController::class, 'preview'])->name('certificates.preview');
    Route::get('/certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::post('/certificates/{certificate}/regenerate', [CertificateController::class, 'regenerate'])->name('certificates.regenerate');
    Route::post('/certificates/{certificate}/sign', [CertificateController::class, 'sign'])->name('certificates.sign');

    Route::get('/badges', [BadgeController::class, 'index'])->name('badges.index');
    Route::post('/badges/section-codes', [BadgeController::class, 'sectionCodes'])->name('badges.section-codes');
    Route::post('/badges', [BadgeController::class, 'store'])->name('badges.store');
    Route::put('/badges/{badge}', [BadgeController::class, 'update'])->name('badges.update');
    Route::delete('/badges/{badge}', [BadgeController::class, 'destroy'])->name('badges.destroy');

    Route::get('/badge-requests', [BadgeRequestController::class, 'index'])->name('badge-requests.index');
    Route::get('/badge-requests/create', [BadgeRequestController::class, 'create'])->name('badge-requests.create');
    Route::post('/badge-requests', [BadgeRequestController::class, 'store'])->name('badge-requests.store');
    Route::get('/badge-requests/{badgeRequest}', [BadgeRequestController::class, 'show'])->name('badge-requests.show');
    Route::post('/badge-requests/{badgeRequest}/approve', [BadgeRequestController::class, 'approve'])->name('badge-requests.approve');
    Route::post('/badge-requests/{badgeRequest}/reject', [BadgeRequestController::class, 'reject'])->name('badge-requests.reject');
    Route::post('/badge-requests/{badgeRequest}/generate', [BadgeRequestController::class, 'generate'])->name('badge-requests.generate');

    Route::get('/certificate-templates', [CertificateTemplateController::class, 'index'])->name('certificate-templates.index');
    Route::post('/certificate-templates', [CertificateTemplateController::class, 'store'])->name('certificate-templates.store');
    Route::post('/certificate-templates/test-slide', [CertificateTemplateController::class, 'testSlide'])->middleware('throttle:integration-tests')->name('certificate-templates.test-slide');
    Route::put('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'update'])->name('certificate-templates.update');
    Route::delete('/certificate-templates/{certificateTemplate}', [CertificateTemplateController::class, 'destroy'])->name('certificate-templates.destroy');
    Route::post('/certificate-templates/{certificateTemplate}/activate', [CertificateTemplateController::class, 'activate'])->name('certificate-templates.activate');
    Route::get('/certificate-templates/{certificateTemplate}/preview', [CertificateTemplateController::class, 'preview'])->name('certificate-templates.preview');

    Route::resource('leadership', LeadershipController::class);
    Route::post('/leadership/{leadership}/generate', [LeadershipController::class, 'generate'])->name('leadership.generate');

    // Events: setup, pre-order items and registrations.
    Route::get('/events/registrations', [EventRegistrationController::class, 'index'])->name('event-registrations.index');
    Route::post('/events/registrations/{registration}/cancel', [EventRegistrationController::class, 'cancel'])->name('event-registrations.cancel');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/status', [EventController::class, 'status'])->name('events.status');
    Route::post('/events/{event}/items', [EventItemController::class, 'store'])->name('events.items.store');
    Route::put('/events/{event}/items/{item}', [EventItemController::class, 'update'])->name('events.items.update');
    Route::delete('/events/{event}/items/{item}', [EventItemController::class, 'destroy'])->name('events.items.destroy');
    Route::post('/events/{event}/register', [EventRegistrationController::class, 'store'])->name('events.register');

    // Reports.
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');

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
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('/settings/google/connect', [GoogleConnectController::class, 'connect'])->name('settings.google.connect');
        Route::get('/settings/google/callback', [GoogleConnectController::class, 'callback'])->name('settings.google.callback');
        Route::post('/settings/google/disconnect', [GoogleConnectController::class, 'disconnect'])->name('settings.google.disconnect');
        Route::post('/settings/test/drive', [SettingsController::class, 'testDrive'])->middleware('throttle:integration-tests')->name('settings.test.drive');
        Route::post('/settings/telegram/test-message', [SettingsController::class, 'sendTelegramTest'])->middleware('throttle:integration-tests')->name('settings.telegram.test-message');
        Route::post('/settings/test/telegram', [SettingsController::class, 'testTelegram'])->middleware('throttle:integration-tests')->name('settings.test.telegram');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/import', [ImportController::class, 'index'])->name('import.index');
        Route::get('/import/template', [ImportController::class, 'template'])->name('import.template');
        Route::post('/import/preview', [ImportController::class, 'preview'])->name('import.preview');
        Route::post('/import/confirm', [ImportController::class, 'confirm'])->name('import.confirm');
        Route::get('/import/errors', [ImportController::class, 'errors'])->name('import.errors');
    });
});
