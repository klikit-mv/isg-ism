<?php

namespace App\Services\Google;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Google authentication, in order of priority:
 *  1. a service-account key in GOOGLE_SERVICE_ACCOUNT_JSON on the server;
 *  2. a Google account connected with "Sign in with Google" (OAuth refresh token);
 *  3. a service-account key file uploaded under Settings.
 * Secrets are stored encrypted in settings.
 */
class GoogleApiClient
{
    private const SCOPES = 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/presentations';

    public const SETTING_KEY = 'google_service_account_json';

    public const OAUTH_CLIENT_ID = 'google_oauth_client_id';

    public const OAUTH_CLIENT_SECRET = 'google_oauth_client_secret';

    public const OAUTH_REFRESH_TOKEN = 'google_oauth_refresh_token';

    public const OAUTH_EMAIL = 'google_oauth_email';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** @var array<string, mixed>|null|false */
    private array|null|false $credentials = false;

    public function __construct(private SettingsService $settings) {}

    /**
     * Where the credentials come from: "server", "oauth", "settings" or null.
     */
    public function source(): ?string
    {
        return match (true) {
            filled(config('services.google.service_account_json')) => 'server',
            $this->oauthConnected() => 'oauth',
            filled($this->settings->get(self::SETTING_KEY)) => 'settings',
            default => null,
        };
    }

    public function usesOauth(): bool
    {
        return $this->source() === 'oauth';
    }

    /**
     * Plain-language hint for making a Drive file reachable.
     */
    public function shareHint(): string
    {
        return $this->usesOauth()
            ? 'Make sure the connected Google account ('.$this->clientEmail().') can edit it.'
            : 'Share it with '.($this->clientEmail() ?? 'the service account').' as an editor.';
    }

    public function oauthClientId(): ?string
    {
        return $this->settings->get(self::OAUTH_CLIENT_ID);
    }

    /**
     * The OAuth client id and secret are saved, so "Connect Google account" can run.
     */
    public function oauthReady(): bool
    {
        return filled($this->oauthClientId()) && filled($this->settings->get(self::OAUTH_CLIENT_SECRET));
    }

    public function oauthConnected(): bool
    {
        return $this->oauthReady() && filled($this->settings->get(self::OAUTH_REFRESH_TOKEN));
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => $this->oauthClientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email '.self::SCOPES,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /**
     * Exchange the authorisation code for a refresh token and remember the account.
     *
     * @return array{ok: bool, message: string}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $this->oauthClientId(),
                'client_secret' => $this->settings->get(self::OAUTH_CLIENT_SECRET),
                'redirect_uri' => $redirectUri,
            ]);
        } catch (Throwable $e) {
            Log::warning('Google code exchange failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Google could not be reached. Please try again.'];
        }

        $refreshToken = $response->json('refresh_token');

        if (! $response->successful() || ! $refreshToken) {
            $error = (string) ($response->json('error_description') ?? $response->json('error') ?? 'unknown error');

            return ['ok' => false, 'message' => 'Google did not return a sign-in token ('.$error.'). Check the Client ID, Client secret and redirect URI, then try again.'];
        }

        $email = $this->emailFromIdToken((string) $response->json('id_token'));

        $this->settings->set(self::OAUTH_REFRESH_TOKEN, (string) $refreshToken);
        $this->settings->set(self::OAUTH_EMAIL, $email);
        $this->forget();

        return ['ok' => true, 'message' => 'Google account '.($email ?? '').' is connected.'];
    }

    public function disconnectOauth(): void
    {
        $token = $this->settings->get(self::OAUTH_REFRESH_TOKEN);

        if ($token) {
            rescue(fn () => Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]), report: false);
            Cache::forget($this->tokenCacheKey());
        }

        $this->settings->set(self::OAUTH_REFRESH_TOKEN, null);
        $this->settings->set(self::OAUTH_EMAIL, null);
        $this->forget();
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
        if ($this->usesOauth()) {
            return true;
        }

        $credentials = $this->credentials();

        return $credentials !== null && isset($credentials['client_email'], $credentials['private_key']);
    }

    /**
     * The Google account used: the service account email or the connected account.
     */
    public function clientEmail(): ?string
    {
        if ($this->usesOauth()) {
            return $this->settings->get(self::OAUTH_EMAIL);
        }

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

        if ($this->usesOauth()) {
            return $this->oauthAccessToken();
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

    private function oauthAccessToken(): ?string
    {
        $token = Cache::get($this->tokenCacheKey());

        if ($token) {
            return $token;
        }

        try {
            $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->settings->get(self::OAUTH_REFRESH_TOKEN),
                'client_id' => $this->oauthClientId(),
                'client_secret' => $this->settings->get(self::OAUTH_CLIENT_SECRET),
            ]);
        } catch (Throwable $e) {
            Log::warning('Google token refresh failed', ['error' => $e->getMessage()]);

            return null;
        }

        $token = $response->successful() ? $response->json('access_token') : null;

        if ($token === null) {
            Log::warning('Google token refresh was refused', ['error' => $response->json('error')]);

            return null;
        }

        Cache::put($this->tokenCacheKey(), $token, max(60, (int) $response->json('expires_in', 3600) - 300));

        return $token;
    }

    private function tokenCacheKey(): string
    {
        return 'google.oauth_token.'.md5((string) $this->settings->get(self::OAUTH_REFRESH_TOKEN));
    }

    private function emailFromIdToken(string $idToken): ?string
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) && filter_var($payload['email'] ?? null, FILTER_VALIDATE_EMAIL) ? $payload['email'] : null;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
