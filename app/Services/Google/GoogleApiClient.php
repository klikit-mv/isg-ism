<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service-account authentication: a self-signed JWT exchanged for an OAuth token.
 */
class GoogleApiClient
{
    private const SCOPES = 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/presentations';

    /** @var array<string, mixed>|null|false */
    private array|null|false $credentials = false;

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

        $raw = (string) config('services.google.service_account_json');

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
