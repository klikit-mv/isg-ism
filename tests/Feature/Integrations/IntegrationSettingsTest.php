<?php

namespace Tests\Feature\Integrations;

use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\ScoutAlert;
use App\Services\NotificationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '123456:ABCdefGhIJKlmNoPQRsTUVwxyZ';

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return array_merge(['default_class_fee' => '50', 'proof_max_kb' => 10240, 'shop_enabled' => '1'], $overrides);
    }

    private function configureGoogle(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
    }

    private function saveTelegram(): void
    {
        Http::fake(['api.telegram.org/*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'ifthithaah_bot']])]);
        $this->actingAs($this->admin())->post('/settings', $this->settingsPayload(['telegram_bot_token' => self::TOKEN]));
    }

    public function test_settings_are_saved_and_cached_values_refresh(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings', $this->settingsPayload([
            'default_class_fee' => '35.5', 'bank_name' => 'Bank of Maldives', 'account_number' => '7701-123456-001', 'footer_text' => 'Be prepared', 'shop_enabled' => '0',
        ]))->assertSessionHas('success');

        $settings = app(SettingsService::class);
        $this->assertSame('35.50', $settings->defaultClassFee());
        $this->assertFalse($settings->shopEnabled());
        $this->actingAs($admin)->get('/dashboard')->assertSee('Be prepared');
    }

    public function test_drive_folder_must_be_a_link_or_id(): void
    {
        $this->actingAs($this->admin())->post('/settings', $this->settingsPayload(['google_drive_folder' => 'my photos']))
            ->assertSessionHasErrors('google_drive_folder');
    }

    public function test_photos_folder_creates_area_folders_when_google_is_configured(): void
    {
        $this->configureGoogle();
        $created = ['STUDENTSFOLDER1', 'SHOPFOLDER00001', 'BADGESFOLDER001', 'SIGNATURESFLDR1'];
        Http::fake(function (Request $request) use (&$created) {
            return match (true) {
                str_contains($request->url(), 'oauth2.googleapis.com') => Http::response(['access_token' => 'token']),
                str_contains($request->url(), 'files/ROOTFOLDER123') => Http::response(['id' => 'ROOTFOLDER123', 'mimeType' => 'application/vnd.google-apps.folder', 'trashed' => false]),
                $request->method() === 'GET' => Http::response(['files' => []]),
                default => Http::response(['id' => array_shift($created)]),
            };
        });

        $this->actingAs($this->admin())->post('/settings', $this->settingsPayload([
            'google_drive_folder' => 'https://drive.google.com/drive/folders/ROOTFOLDER123',
        ]))->assertSessionHas('success');

        $settings = app(SettingsService::class);
        $this->assertSame('ROOTFOLDER123', $settings->driveFolderId());
        $this->assertSame('STUDENTSFOLDER1', $settings->driveAreaFolderId('students'));
        $this->assertSame('SIGNATURESFLDR1', $settings->driveAreaFolderId('signatures'));
        $this->assertStringContainsString('Drive is ready', session('success'));
    }

    public function test_without_google_credentials_photos_stay_local(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin())->post('/settings', $this->settingsPayload(['google_drive_folder' => 'ROOTFOLDER123']))
            ->assertSessionHas('success');
        $student = Student::factory()->create();

        $this->actingAs($this->admin())->post("/students/{$student->uuid}/photo", ['photo' => UploadedFile::fake()->image('p.jpg')]);

        $this->assertStringStartsWith('students/', $student->fresh()->photo_path);
    }

    public function test_drive_photos_are_served_to_signed_in_users_only(): void
    {
        $this->configureGoogle();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'www.googleapis.com/drive/v3/files/PHOTOFILE12345*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')),
        ]);

        $this->get('/photos/PHOTOFILE12345')->assertRedirect(route('login'));
        $this->actingAs($this->parentOf())->get('/photos/PHOTOFILE12345')
            ->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_telegram_token_is_validated_encrypted_and_never_echoed(): void
    {
        $this->saveTelegram();

        $stored = Setting::query()->where('key', 'telegram_bot_token')->value('value');
        $this->assertNotSame(self::TOKEN, $stored);
        $this->assertSame(self::TOKEN, app(SettingsService::class)->telegramBotToken());
        $this->assertSame('ifthithaah_bot', app(SettingsService::class)->telegramBotUsername());

        $this->actingAs($this->admin())->get('/settings')->assertOk()->assertDontSee(self::TOKEN)->assertSee('@ifthithaah_bot');
        $this->assertDatabaseMissing('audit_logs', ['details' => json_encode(['telegram_bot_token' => self::TOKEN])]);
    }

    public function test_a_bad_telegram_token_is_refused(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 401)]);

        $this->actingAs($this->admin())->post('/settings', $this->settingsPayload(['telegram_bot_token' => 'nope']))
            ->assertSessionHasErrors('telegram_bot_token');
        $this->assertNull(app(SettingsService::class)->telegramBotToken());
    }

    public function test_user_connects_telegram_and_receives_notifications_there(): void
    {
        $this->saveTelegram();
        $user = $this->parentOf();

        $this->actingAs($user)->post('/profile/telegram/connect')->assertSessionHas('success');
        $plain = session('telegram_connect_token');
        $this->assertSame(32, strlen($plain));
        $this->assertSame(hash('sha256', $plain), $user->fresh()->telegram_connect_token);

        Http::fake([
            'api.telegram.org/*/getUpdates' => Http::response(['ok' => true, 'result' => [
                ['update_id' => 10, 'message' => ['text' => '/start wrongtoken', 'chat' => ['id' => 111]]],
                ['update_id' => 11, 'message' => ['text' => '/start '.$plain, 'chat' => ['id' => 424242]]],
            ]]),
            'api.telegram.org/*/sendMessage' => Http::response(['ok' => true]),
        ]);

        $this->actingAs($user)->post('/profile/telegram/confirm')->assertSessionHas('success', 'Telegram is connected.');
        $user->refresh();
        $this->assertSame('424242', $user->telegram_chat_id);
        $this->assertTrue($user->telegram_notifications_enabled);
        $this->assertNull($user->telegram_connect_token);
        $this->assertSame(12, app(SettingsService::class)->telegramUpdateOffset());

        $this->assertContains(TelegramChannel::class, (new ScoutAlert('t', 'b'))->via($user));
        app(NotificationService::class)->send($user, 'Hello', 'From the portal');
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage') && $request['chat_id'] === '424242');

        $this->actingAs($user)->post('/profile/telegram/disconnect');
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_expired_connect_token_does_not_link(): void
    {
        $this->saveTelegram();
        $user = $this->parentOf();
        $this->actingAs($user)->post('/profile/telegram/connect');
        $plain = session('telegram_connect_token');
        $this->travel(31)->minutes();

        Http::fake(['api.telegram.org/*/getUpdates' => Http::response(['ok' => true, 'result' => [['update_id' => 1, 'message' => ['text' => '/start '.$plain, 'chat' => ['id' => 5]]]]])]);

        $this->actingAs($user)->post('/profile/telegram/confirm')->assertSessionHas('warning');
        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_telegram_failures_never_break_a_request(): void
    {
        $this->saveTelegram();
        $user = User::factory()->create(['telegram_chat_id' => '99', 'telegram_notifications_enabled' => true]);
        Http::fake(fn () => throw new ConnectionException('down'));

        app(NotificationService::class)->send($user, 'Hello', 'Body');

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_connection_tests_are_admin_only(): void
    {
        $this->actingAs($this->leader())->post('/settings/test/drive')->assertForbidden();
        $this->actingAs($this->leader())->post('/settings/test/telegram')->assertForbidden();
        $this->actingAs($this->admin())->post('/settings/test/drive')->assertSessionHas('error');
        $this->actingAs($this->admin())->post('/settings/test/telegram')->assertSessionHas('error', 'No Telegram bot token is saved yet.');
    }

    public function test_email_notifications_follow_the_profile_toggle(): void
    {
        $user = User::factory()->create(['email_notifications_enabled' => true]);
        $this->assertContains('mail', (new ScoutAlert('t', 'b'))->via($user));

        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'email_notifications_enabled' => '0']);
        $this->assertNotContains('mail', (new ScoutAlert('t', 'b'))->via($user->fresh()));
    }
}
