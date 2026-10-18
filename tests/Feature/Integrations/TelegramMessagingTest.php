<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Notifications\ScoutAlert;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function configureBot(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('telegram_bot_token', '123456:TOKEN');
        $settings->set('telegram_bot_username', 'ifthithaah_bot');
    }

    public function test_admin_sends_a_test_message_to_a_connected_person(): void
    {
        $this->configureBot();
        $person = User::factory()->create(['name' => 'Aisha', 'telegram_chat_id' => '555111']);
        Http::fake(['api.telegram.org/*/sendMessage' => Http::response(['ok' => true])]);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/settings')->assertSee('data-testid="telegram-test-message"', false)->assertSee('Aisha');

        $this->actingAs($admin)->post('/settings/telegram/test-message', ['recipient' => $person->uuid, 'message' => 'Hi <there>'])
            ->assertSessionHas('success', 'Test message sent to Aisha.');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendMessage')
            && $r['chat_id'] === '555111'
            && $r['parse_mode'] === 'HTML'
            && str_contains($r['text'], 'Hi &lt;there&gt;'));
    }

    public function test_admin_sends_to_a_chat_id_and_telegram_errors_are_explained(): void
    {
        $this->configureBot();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->actingAs($this->admin())->post('/settings/telegram/test-message', ['chat_id' => '987654', 'message' => 'Hello'])
            ->assertSessionHas('error', 'Could not send to 987654: Telegram does not know that chat. The person must open the bot and press Start first.');
    }

    public function test_a_recipient_is_required_and_leaders_cannot_send(): void
    {
        $this->configureBot();

        $this->actingAs($this->admin())->post('/settings/telegram/test-message', ['message' => 'Hello'])->assertSessionHasErrors('recipient');
        $this->actingAs($this->admin())->post('/settings/telegram/test-message', ['chat_id' => 'not an id', 'message' => 'Hello'])->assertSessionHasErrors('chat_id');
        $this->actingAs($this->leader())->post('/settings/telegram/test-message', ['chat_id' => '1', 'message' => 'Hello'])->assertForbidden();
    }

    public function test_confirm_reports_json_while_waiting_and_welcomes_once_connected(): void
    {
        $this->configureBot();
        $user = $this->parentOf();
        $token = null;
        $polls = 0;
        Http::fake([
            'api.telegram.org/*/getUpdates' => function () use (&$token, &$polls) {
                // The first poll sees nothing; the second sees the user's /start.
                return ++$polls === 1
                    ? Http::response(['ok' => true, 'result' => []])
                    : Http::response(['ok' => true, 'result' => [['update_id' => 7, 'message' => ['text' => '/start '.$token, 'chat' => ['id' => 4242]]]]]);
            },
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($user)->post('/profile/telegram/connect');
        $token = session('telegram_connect_token');
        $this->actingAs($user)->get('/profile')->assertSee('data-testid="telegram-open"', false)->assertSee('https://t.me/ifthithaah_bot?start='.$token, false);

        $this->actingAs($user)->postJson('/profile/telegram/confirm')->assertExactJson(['connected' => false]);

        $this->actingAs($user)->postJson('/profile/telegram/confirm')->assertExactJson(['connected' => true]);

        $this->assertSame('4242', $user->fresh()->telegram_chat_id);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'sendMessage') && $r['chat_id'] === '4242' && str_contains($r['text'], 'your account is connected'));
    }

    public function test_connect_without_a_bot_explains_why(): void
    {
        $this->actingAs($this->parentOf())->post('/profile/telegram/connect')
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');
    }

    public function test_notifications_are_sent_as_escaped_html(): void
    {
        $user = User::factory()->create(['name' => 'A_B*C']);

        $text = (new ScoutAlert('Payment <approved>', 'For Ali_Hassan & co', 'https://portal.test/p'))->toTelegram($user);

        $this->assertSame("<b>Payment &lt;approved&gt;</b>\nFor Ali_Hassan &amp; co\nhttps://portal.test/p", $text);
    }
}
