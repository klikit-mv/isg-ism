<?php

namespace App\Services\Google;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service-account authentication: a self-signed JWT exchanged for an OAuth token.
 * The key comes from GOOGLE_SERVICE_ACCOUNT_JSON on the server, or else from
 * the key file an admin uploads under Settings (stored encrypted).
 */
class GoogleApiClient
{
    private const SCOPES = 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/presentations';

    public const SETTING_KEY = 'google_service_account_json';

    /** @var array<string, mixed>|null|false */
    private array|null|false $credentials = false;

    public function __construct(private SettingsService $settings) {}

    /**
     * Where the credentials come from: "server", "settings" or null.
     */
    public function source(): ?string
    {
        if (filled(config('services.google.service_account_json'))) {
            return 'server';
        }

        return filled($this->settings->get(self::SETTING_KEY)) ? 'settings' : null;
    }

    public function forget(): void
    {
        $this->credentials = false;
    }

    /**
     * Check an uploaded service-account key. Returns the decoded key or an error message.
     *
     * @return array<string, mixed>|string
     */
    public static function parseKey(string $json): array|string
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return 'That file is not valid JSON. Download the key again from Google Cloud (Keys → Add key → JSON).';
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            return 'That JSON is not a service account key. It must contain "type": "service_account".';
        }

        if (! filter_var($decoded['client_email'] ?? null, FILTER_VALIDATE_EMAIL) || ! str_contains((string) ($decoded['private_key'] ?? ''), 'PRIVATE KEY')) {
            return 'The key is missing its client_email or private_key.';
        }

        if (openssl_pkey_get_private((string) $decoded['private_key']) === false) {
            return 'The private key in that file could not be read.';
        }

        return $decoded;
    }

    public function configured(): bool
    {
        $credentials = $this->credentials();

        return $credentials !== null && isset($credentials['client_email'], $credentials['private_key']);
    }

    public function clientEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function credentials(): ?array
    {
        if ($this->credentials !== false) {
            return $this->credentials;
        }

        $raw = (string) (config('services.google.service_account_json') ?: $this->settings->get(self::SETTING_KEY));

        if ($raw === '') {
            return $this->credentials = null;
        }

        if (! str_starts_with(ltrim($raw), '{') && is_file($raw)) {
            $raw = (string) file_get_contents($raw);
        }

        $decoded = json_decode($raw, true);

        return $this->credentials = is_array($decoded) ? $decoded : null;
    }

    public function accessToken(): ?string
    {
        if (! $this->configured()) {
            return null;
        }

        return Cache::remember('google.access_token.'.md5((string) $this->clientEmail()), 3000, function (): ?string {
            try {
                $credentials = (array) $this->credentials();
                $now = time();
                $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
                $claims = $this->base64Url((string) json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => self::SCOPES,
                    'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now,
                    'exp' => $now + 3600,
                ]));

                $signature = '';
                openssl_sign($header.'.'.$claims, $signature, (string) $credentials['private_key'], OPENSSL_ALGO_SHA256);
                $jwt = $header.'.'.$claims.'.'.$this->base64Url($signature);

                $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);

                return $response->successful() ? $response->json('access_token') : null;
            } catch (Throwable $e) {
                Log::warning('Google token request failed', ['error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
