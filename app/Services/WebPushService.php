<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Browser push notifications (Android, desktop and installed iOS apps). The server's VAPID key pair is created on first
 * use and kept in the settings (private key encrypted). Failures are logged and never break a request.
 */
class WebPushService
{
    public function __construct(private SettingsService $settings) {}

    /**
     * The server's VAPID key pair: from VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY when set, otherwise created on first use and
     * kept in the settings. Null when PHP's OpenSSL cannot create keys (push is then simply unavailable).
     *
     * @return array{publicKey: string, privateKey: string}|null
     */
    public function keys(): ?array
    {
        if (filled(config('scout.vapid_public_key')) && filled(config('scout.vapid_private_key'))) {
            return ['publicKey' => (string) config('scout.vapid_public_key'), 'privateKey' => (string) config('scout.vapid_private_key')];
        }

        $public = $this->settings->get('webpush_public_key');
        $private = $this->settings->get('webpush_private_key');

        if ($public && $private) {
            return ['publicKey' => $public, 'privateKey' => $private];
        }

        try {
            $pair = $this->generateKeys();
        } catch (Throwable $e) {
            Log::warning('Push notifications unavailable: the VAPID keys could not be created ('.$e->getMessage().'). Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY (php artisan webpush:keys) or fix PHP OpenSSL.');

            return null;
        }

        $this->settings->set('webpush_public_key', $pair['publicKey']);
        $this->settings->set('webpush_private_key', $pair['privateKey']);

        return $pair;
    }

    /**
     * @return array{publicKey: string, privateKey: string}
     */
    public function generateKeys(): array
    {
        return VAPID::createVapidKeys();
    }

    public function publicKey(): ?string
    {
        return $this->keys()['publicKey'] ?? null;
    }

    /**
     * @param  array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string}  $data
     */
    public function subscribe(User $user, array $data, ?string $userAgent = null): PushSubscription
    {
        return PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 255) : null,
            ],
        );
    }

    public function unsubscribe(User $user, string $endpoint): void
    {
        PushSubscription::query()->where('user_id', $user->id)->where('endpoint_hash', hash('sha256', $endpoint))->delete();
    }

    public function hasSubscriptions(User $user): bool
    {
        return PushSubscription::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Send to every device of the user. Subscriptions the push service reports as gone are removed.
     *
     * @return int number of devices reached
     */
    public function send(User $user, string $title, string $body, ?string $url = null): int
    {
        $subscriptions = PushSubscription::query()->where('user_id', $user->id)->get();

        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $payload = json_encode(['title' => $title, 'body' => mb_substr($body, 0, 240), 'url' => $url ?: '/dashboard'], JSON_UNESCAPED_UNICODE);
        $reached = 0;

        try {
            $client = $this->client();

            foreach ($subscriptions as $subscription) {
                $client->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), $payload);
            }

            foreach ($client->flush() as $report) {
                if ($report->isSuccess()) {
                    $reached++;
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', hash('sha256', $report->getEndpoint()))->delete();
                } else {
                    Log::warning('Push notification failed: '.$report->getReason());
                }
            }
        } catch (Throwable $e) {
            Log::warning('Push notification failed: '.$e->getMessage());
        }

        return $reached;
    }

    protected function client(): WebPush
    {
        $keys = $this->keys() ?? throw new \RuntimeException('Push notification keys are not available.');

        return new WebPush(['VAPID' => [
            'subject' => config('app.url') ?: 'mailto:admin@example.org',
            'publicKey' => $keys['publicKey'],
            'privateKey' => $keys['privateKey'],
        ]], ['TTL' => 86400], 8);
    }
}
