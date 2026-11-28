<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const ACCOUNT_ATTEMPTS = 10;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['national_id' => Str::upper(trim((string) $this->input('national_id')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'national_id' => ['required', 'string', 'max:64'],
            'pin' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * Check National ID + PIN, accepting a legacy SHA-256 PIN once and rehashing it.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        $user = User::query()->where('national_id', $this->input('national_id'))->first();
        $pin = (string) $this->input('pin');

        if ($user === null || ! $this->pinMatches($user, $pin)) {
            $this->hitLimiters();

            throw ValidationException::withMessages([
                'national_id' => 'These details do not match an active account.',
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            $this->hitLimiters();

            $message = $user->verified_at === null
                ? 'Your registration is waiting for a leader to verify it.'
                : 'These details do not match an active account.';

            throw ValidationException::withMessages(['national_id' => $message]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountKey());
        Auth::login($user, $this->boolean('remember'));

        return $user;
    }

    private function pinMatches(User $user, string $pin): bool
    {
        if ($user->legacy_pin_hash === null && $this->isBcrypt($user->password) && Hash::check($pin, $user->password)) {
            return true;
        }

        if ($user->legacy_pin_hash !== null && $this->legacyMatches($user->legacy_pin_hash, (string) $user->legacy_pin_salt, $pin)) {
            $user->forceFill([
                'password' => $pin,
                'legacy_pin_hash' => null,
                'legacy_pin_salt' => null,
            ])->save();

            app(AuditLogService::class)->record('auth.pin_rehashed', $user, [], $user);

            return true;
        }

        return $this->isBcrypt($user->password) && Hash::check($pin, $user->password);
    }

    private function legacyMatches(string $hash, string $salt, string $pin): bool
    {
        $hash = strtolower($hash);

        foreach ([$salt.$pin, $pin.$salt, $pin] as $candidate) {
            if (hash_equals($hash, hash('sha256', $candidate))) {
                return true;
            }
        }

        return false;
    }

    private function isBcrypt(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, '$2');
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), 5) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->accountKey(), self::ACCOUNT_ATTEMPTS) => $this->accountKey(),
            default => null,
        };

        if ($key === null) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'national_id' => "Too many sign-in attempts. Please try again in {$seconds} seconds.",
        ]);
    }

    /**
     * Failed attempts are also counted per account across every address, so a six-digit PIN cannot be guessed by
     * spreading attempts over many IPs.
     */
    private function hitLimiters(): void
    {
        RateLimiter::hit($this->throttleKey());
        RateLimiter::hit($this->accountKey(), 900);
    }

    public function accountKey(): string
    {
        return 'login-account|'.Str::transliterate(Str::lower((string) $this->input('national_id')));
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower((string) $this->input('national_id')).'|'.$this->ip());
    }
}
