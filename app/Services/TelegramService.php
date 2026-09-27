<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Telegram bot integration. Linking polls getUpdates, so the bot must not
 * have a webhook set; every failure logs a warning and never breaks a request.
 */
class TelegramService
{
    private const API = 'https://api.telegram.org';

    public function __construct(private SettingsService $settings) {}

    public function configured(): bool
    {
        return filled($this->token());
    }

    public function token(): ?string
    {
        return $this->settings->telegramBotToken();
    }

    public function botUsername(): ?string
    {
        return $this->settings->telegramBotUsername();
    }

    public function deepLink(string $token): ?string
    {
        $username = $this->botUsername();

        return $username ? 'https://t.me/'.$username.'?start='.$token : null;
    }

    /**
     * Validate a bot token with getMe, then store it encrypted with the bot username.
     *
     * @return array{ok: bool, message: string}
     */
    public function storeToken(string $token, ?User $actor = null): array
    {
        $result = $this->call($token, 'getMe');

        if (! ($result['ok'] ?? false)) {
            return ['ok' => false, 'message' => 'Telegram did not accept that bot token.'];
        }

        $this->settings->set('telegram_bot_token', $token, $actor);
        $this->settings->set('telegram_bot_username', (string) ($result['result']['username'] ?? ''), $actor);
        $this->settings->set('telegram_update_offset', '0', $actor);

        return ['ok' => true, 'message' => 'Bot @'.($result['result']['username'] ?? '').' saved.'];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testConnection(): array
    {
        if (! $this->configured()) {
            return ['ok' => false, 'message' => 'No Telegram bot token is saved yet.'];
        }

        $result = $this->call((string) $this->token(), 'getMe');

        return ($result['ok'] ?? false)
            ? ['ok' => true, 'message' => 'Telegram is reachable. Bot @'.($result['result']['username'] ?? '').' is ready.']
            : ['ok' => false, 'message' => 'Telegram could not be reached with the saved token.'];
    }

    /**
     * Issue a 30-minute connect token; only its SHA-256 is stored.
     */
    public function issueConnectToken(User $user): string
    {
        $token = Str::random(32);

        $user->forceFill([
            'telegram_connect_token' => hash('sha256', $token),
            'telegram_connect_token_expires_at' => now()->addMinutes(30),
        ])->save();

        return $token;
    }

    public function connectUrlFor(string $token): ?string
    {
        return $this->deepLink($token);
    }

    /**
     * Poll getUpdates and link any users whose /start token matches.
     *
     * @return int number of users linked
     */
    public function claimPendingStarts(): int
    {
        if (! $this->configured()) {
            return 0;
        }

        $offset = $this->settings->telegramUpdateOffset();
        $result = $this->call((string) $this->token(), 'getUpdates', ['offset' => $offset, 'timeout' => 0]);

        if (! ($result['ok'] ?? false)) {
            return 0;
        }

        $linked = 0;
        $maxId = $offset - 1;

        foreach ($result['result'] ?? [] as $update) {
            $maxId = max($maxId, (int) ($update['update_id'] ?? 0));
            $text = (string) ($update['message']['text'] ?? '');
            $chatId = $update['message']['chat']['id'] ?? null;

            if ($chatId === null || ! preg_match('/^\/start\s+(\S+)/', $text, $m)) {
                continue;
            }

            $user = User::query()
                ->where('telegram_connect_token', hash('sha256', $m[1]))
                ->where('telegram_connect_token_expires_at', '>', now())
                ->first();

            if ($user) {
                $user->forceFill([
                    'telegram_chat_id' => (string) $chatId,
                    'telegram_notifications_enabled' => true,
                    'telegram_connect_token' => null,
                    'telegram_connect_token_expires_at' => null,
                ])->save();
                $linked++;
            }
        }

        if ($maxId >= $offset) {
            $this->settings->set('telegram_update_offset', (string) ($maxId + 1));
        }

        return $linked;
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        if (! $this->configured()) {
            return false;
        }

        $result = $this->call((string) $this->token(), 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ]);

        return (bool) ($result['ok'] ?? false);
    }

    public function disconnect(User $user): void
    {
        $user->forceFill([
            'telegram_chat_id' => null,
            'telegram_notifications_enabled' => false,
            'telegram_connect_token' => null,
            'telegram_connect_token_expires_at' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $token, string $method, array $payload = []): array
    {
        try {
            $response = Http::timeout(20)->asJson()->post(self::API.'/bot'.$token.'/'.$method, $payload);

            return (array) $response->json();
        } catch (Throwable $e) {
            Log::warning('Telegram request failed', ['method' => $method, 'error' => $e->getMessage()]);

            return ['ok' => false];
        }
    }
}
