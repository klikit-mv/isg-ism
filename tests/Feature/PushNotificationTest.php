<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ScoutAlert;
use App\Services\SettingsService;
use App\Services\WebPushService;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $endpoint = 'https://push.example.com/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey', 'auth' => 'auth']];
    }

    public function test_a_device_can_subscribe_and_unsubscribe(): void
    {
        $user = User::factory()->parentRole()->create();

        $this->actingAs($user)->postJson(route('profile.push.subscribe'), $this->payload())->assertOk();
        $this->actingAs($user)->postJson(route('profile.push.subscribe'), $this->payload())->assertOk();
        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->id)->count());

        $this->actingAs($user)->deleteJson(route('profile.push.unsubscribe'), ['endpoint' => 'https://push.example.com/abc'])->assertOk();
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_only_https_endpoints_are_accepted_and_sign_in_is_required(): void
    {
        $user = User::factory()->parentRole()->create();

        $this->actingAs($user)->postJson(route('profile.push.subscribe'), $this->payload('http://push.example.com/abc'))->assertUnprocessable();
        auth()->logout();
        $this->postJson(route('profile.push.subscribe'), $this->payload())->assertUnauthorized();
    }

    public function test_the_profile_page_offers_notifications_and_creates_the_server_keys_once(): void
    {
        $user = User::factory()->parentRole()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('Turn on notifications');
        $key = app(WebPushService::class)->publicKey();
        $this->assertSame($key, app(WebPushService::class)->publicKey());
    }

    public function test_the_installed_app_offers_to_turn_notifications_on_after_sign_in(): void
    {
        $this->actingAs(User::factory()->parentRole()->create())->get(route('dashboard'))->assertOk()->assertSee('data-testid="push-banner"', false)->assertSee('scoutPushBanner', false);
    }

    public function test_pages_still_work_when_the_server_cannot_create_push_keys(): void
    {
        $this->app->bind(WebPushService::class, fn () => new class(app(SettingsService::class)) extends WebPushService
        {
            public function generateKeys(): array
            {
                throw new \RuntimeException('Unable to create the key');
            }
        });
        $user = User::factory()->parentRole()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $this->actingAs($user)->getJson(route('profile.push.key'))->assertStatus(503);
        $this->assertSame(0, app(WebPushService::class)->send($user, 'Hi', 'Body'));
    }

    public function test_keys_from_the_environment_are_used_when_set(): void
    {
        config(['scout.vapid_public_key' => 'PUB', 'scout.vapid_private_key' => 'PRIV']);

        $this->actingAs(User::factory()->parentRole()->create())->getJson(route('profile.push.key'))->assertOk()->assertJson(['key' => 'PUB']);
    }

    public function test_the_test_notification_reports_why_it_could_not_be_delivered(): void
    {
        $user = User::factory()->parentRole()->create();

        $this->actingAs($user)->postJson(route('profile.push.test'))->assertOk()->assertJson(['sent' => 0])->assertJsonPath('error', fn ($error) => str_contains($error, 'not registered'));
    }

    public function test_alerts_go_to_devices_only_for_people_who_turned_push_on(): void
    {
        $with = User::factory()->parentRole()->create();
        $without = User::factory()->parentRole()->create();
        app(WebPushService::class)->subscribe($with, $this->payload());

        $this->assertContains(WebPushChannel::class, (new ScoutAlert('Hi', 'Body'))->via($with));
        $this->assertNotContains(WebPushChannel::class, (new ScoutAlert('Hi', 'Body'))->via($without));
    }

    public function test_a_subscription_the_push_service_reports_gone_is_removed(): void
    {
        $user = User::factory()->parentRole()->create();
        app(WebPushService::class)->subscribe($user, $this->payload());

        $gone = new MessageSentReport(Mockery::mock(RequestInterface::class, ['getUri' => new Uri('https://push.example.com/abc')]), Mockery::mock(ResponseInterface::class, ['getStatusCode' => 410]), false, 'gone');
        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('queueNotification')->once();
        $client->shouldReceive('flush')->once()->andReturn((fn () => yield $gone)());

        $service = new class(app(SettingsService::class), $client) extends WebPushService
        {
            public function __construct(SettingsService $settings, private WebPush $fake)
            {
                parent::__construct($settings);
            }

            protected function client(): WebPush
            {
                return $this->fake;
            }
        };

        $this->assertSame(0, $service->send($user, 'Hi', 'Body'));
        $this->assertSame(0, PushSubscription::query()->count());
    }
}
