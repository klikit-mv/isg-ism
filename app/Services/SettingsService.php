<?php

namespace App\Services;

use App\Enums\ScoutSection;
use App\Models\Setting;
use App\Models\User;
use App\Support\Money;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Key/value settings, cached for 60 seconds.
 */
class SettingsService
{
    public const CACHE_KEY = 'scout.settings';

    public const ENCRYPTED = ['telegram_bot_token', 'google_service_account_json', 'google_oauth_client_secret', 'google_oauth_refresh_token', 'webpush_private_key'];

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => Setting::query()->pluck('value', 'key')->all());
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $value = $this->all()[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        if (in_array($key, self::ENCRYPTED, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return $default;
            }
        }

        return $value;
    }

    public function set(string $key, ?string $value, ?User $actor = null): void
    {
        if ($value !== null && $value !== '' && in_array($key, self::ENCRYPTED, true)) {
            $value = Crypt::encryptString($value);
        }

        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor?->id]);
        $this->flush();
    }

    /**
     * The certificate number code shared by every proficiency badge of a section (e.g. SCOUT).
     */
    public function sectionBadgeCode(ScoutSection $section): string
    {
        return strtoupper($this->get('badge_code_'.Str::slug($section->value, '_')) ?: $section->numberPrefix());
    }

    public function forget(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function defaultClassFee(): string
    {
        return Money::normalize($this->get('default_class_fee', (string) config('scout.default_class_fee')));
    }

    /**
     * Whether scouts must give an email address when they are registered or enrolled.
     */
    public function studentEmailRequired(): bool
    {
        return $this->get('student_email_required', '1') === '1';
    }

    public function shopEnabled(): bool
    {
        return $this->get('shop_enabled', '1') === '1';
    }

    public function proofMaxKb(): int
    {
        return (int) $this->get('proof_max_kb', (string) config('scout.proof_max_kb'));
    }

    public function proofMaxBytes(): int
    {
        return $this->proofMaxKb() * 1024;
    }

    /**
     * @return list<string>
     */
    public function proofMimes(): array
    {
        $raw = $this->get('proof_mimes', 'png,jpg,jpeg,pdf');

        return array_values(array_filter(array_map('trim', explode(',', strtolower((string) $raw)))));
    }

    public function bankName(): ?string
    {
        return $this->get('bank_name');
    }

    public function accountName(): ?string
    {
        return $this->get('account_name');
    }

    public function accountNumber(): ?string
    {
        return $this->get('account_number');
    }

    public function paymentInstructions(): ?string
    {
        return $this->get('payment_instructions');
    }

    /**
     * Public-disk path of the uploaded website logo, if any.
     */
    public function logoPath(): ?string
    {
        $path = $this->get('site_logo_path');

        return $path && Storage::disk('public')->exists($path) ? $path : null;
    }

    public function logoUrl(): ?string
    {
        $path = $this->logoPath();

        return $path ? route('branding.logo', ['v' => substr(md5($path), 0, 8)], false) : null;
    }

    /**
     * The uploaded logo as a data URI (used on certificates), or null.
     */
    public function logoDataUri(): ?string
    {
        $path = $this->logoPath();

        if ($path === null) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) Storage::disk('public')->get($path));
    }

    public function footerText(): string
    {
        return (string) $this->get('footer_text', '© '.scout_now()->format('Y').' '.config('scout.name'));
    }

    public function driveFolderId(): ?string
    {
        return $this->get('google_drive_folder_id');
    }

    public function driveAreaFolderId(string $area): ?string
    {
        return $this->get('google_drive_folder_'.$area);
    }

    public function drivePaymentsFolderId(): ?string
    {
        return $this->get('google_drive_payments_folder_id');
    }

    public function driveCertificatesFolderId(): ?string
    {
        return $this->get('google_drive_certificates_folder_id');
    }

    public function telegramBotToken(): ?string
    {
        return $this->get('telegram_bot_token');
    }

    public function telegramBotUsername(): ?string
    {
        return $this->get('telegram_bot_username');
    }

    public function telegramUpdateOffset(): int
    {
        return (int) $this->get('telegram_update_offset', '0');
    }
}
