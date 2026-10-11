<?php

namespace App\Providers;

use App\Models\AnnualFee;
use App\Models\ClassFee;
use App\Models\EventRegistration;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Certificates\CertificateDocumentRenderer;
use App\Services\Certificates\DompdfCertificateRenderer;
use App\Services\SettingsService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SettingsService::class);
        $this->app->bind(CertificateDocumentRenderer::class, DompdfCertificateRenderer::class);
    }

    public function boot(): void
    {
        Relation::morphMap([
            'class_fee' => ClassFee::class,
            'annual_fee' => AnnualFee::class,
            'purchase' => Purchase::class,
            'event_registration' => EventRegistration::class,
            'user' => User::class,
        ]);

        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);
        Gate::define('admin', fn (User $user) => false);
        Gate::define('staff', fn (User $user) => $user->isActive() && $user->isLeader());

        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(8)->by($request->ip()));
        RateLimiter::for('pin-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('child-lookup', fn (Request $request) => Limit::perMinute(40)->by($request->ip()));
        RateLimiter::for('certificate-verify', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('integration-tests', fn (Request $request) => Limit::perMinute(8)->by($request->user()?->id ?: $request->ip()));
    }
}
