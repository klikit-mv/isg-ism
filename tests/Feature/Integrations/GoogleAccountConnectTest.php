<?php

namespace Tests\Feature\Integrations;

use App\Models\Setting;
use App\Services\Google\GoogleApiClient;
use App\Services\Google\GoogleDriveClient;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleAccountConnectTest extends TestCase
{
    use RefreshDatabase;

    private const CLIENT_ID = '1234-abcdef.apps.googleusercontent.com';

    private const SECRET = 'GOCSPX-super-secret-value';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.service_account_json' => null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['default_class_fee' => '50', 'proof_max_kb' => 10240, 'shop_enabled' => '1'], $overrides);
    }

    private function idToken(string $email): string
    {
        $part = fn (array $data) => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');

        return $part(['alg' => 'RS256']).'.'.$part(['email' => $email]).'.signature';
    }

    private function saveClient(): void
    {
        $this->actingAs($this->admin())->post('/settings', $this->payload([
            'google_oauth_client_id' => self::CLIENT_ID,
            'google_oauth_client_secret' => self::SECRET,
        ]))->assertSessionHasNoErrors();
    }

    private function connect(string $email = 'scouts@example.org'): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'first-access-token',
                'refresh_token' => 'the-refresh-token',
                'expires_in' => 3600,
                'id_token' => $this->idToken($email),
            ]),
        ]);

        $redirect = $this->get('/settings/google/connect')->headers->get('Location');
        parse_str((string) parse_url((string) $redirect, PHP_URL_QUERY), $query);

        $this->get('/settings/google/callback?code=auth-code&state='.$query['state'])
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('success');
    }

    public function test_client_id_and_secret_are_saved_with_the_secret_encrypted_and_hidden(): void
    {
        $this->saveClient();

        $this->assertTrue(app(GoogleApiClient::class)->oauthReady());
        $this->assertNotSame(self::SECRET, Setting::query()->where('key', GoogleApiClient::OAUTH_CLIENT_SECRET)->value('value'));
        $this->get('/settings')
            ->assertSee(self::CLIENT_ID)
            ->assertDontSee(self::SECRET)
            ->assertSee(route('settings.google.callback'))
            ->assertSee('Connect Google account');
    }

    public function test_client_id_must_look_like_a_google_client_id(): void
    {
        $this->actingAs($this->admin())->post('/settings', $this->payload(['google_oauth_client_id' => 'AIzaSyAPIKEY']))
            ->assertSessionHasErrors('google_oauth_client_id');
    }

    public function test_connect_sends_the_admin_to_google_with_offline_access(): void
    {
        $this->saveClient();

        $location = (string) $this->get('/settings/google/connect')->headers->get('Location');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(self::CLIENT_ID, $query['client_id']);
        $this->assertSame(route('settings.google.callback'), $query['redirect_uri']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/drive', $query['scope']);
        $this->assertSame(40, strlen($query['state']));
    }

    public function test_connecting_stores_the_account_and_drive_calls_use_it(): void
    {
        $this->saveClient();
        $this->connect('scouts@example.org');

        $google = app(GoogleApiClient::class);
        $this->assertTrue($google->configured());
        $this->assertSame('oauth', $google->source());
        $this->assertSame('scouts@example.org', $google->clientEmail());
        $this->assertNotSame('the-refresh-token', Setting::query()->where('key', GoogleApiClient::OAUTH_REFRESH_TOKEN)->value('value'));
        Http::assertSent(fn (Request $r) => ($r->data()['grant_type'] ?? null) === 'authorization_code' && ($r->data()['client_secret'] ?? null) === self::SECRET);

        // The token stub from connect() still answers refresh requests.
        Http::fake([
            'www.googleapis.com/drive/v3/files/FOLDER123456*' => Http::response(['id' => 'FOLDER123456', 'mimeType' => 'application/vnd.google-apps.folder', 'trashed' => false]),
        ]);

        $this->assertTrue(app(GoogleDriveClient::class)->folderAccessible('FOLDER123456'));
        Http::assertSent(fn (Request $r) => ($r->data()['grant_type'] ?? null) === 'refresh_token' && ($r->data()['refresh_token'] ?? null) === 'the-refresh-token');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'FOLDER123456') && $r->hasHeader('Authorization', 'Bearer first-access-token'));

        $this->get('/settings')->assertSee('data-testid="google-connected"', false)->assertSee('scouts@example.org');
    }

    public function test_callback_with_a_wrong_state_is_refused(): void
    {
        $this->saveClient();
        $this->get('/settings/google/connect');
        Http::fake();

        $this->get('/settings/google/callback?code=x&state=forged')->assertSessionHas('error');

        Http::assertNothingSent();
        $this->assertFalse(app(GoogleApiClient::class)->oauthConnected());
    }

    public function test_a_refused_code_is_explained(): void
    {
        $this->saveClient();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad Request'], 400)]);
        $location = (string) $this->get('/settings/google/connect')->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->get('/settings/google/callback?code=bad&state='.$query['state'])->assertSessionHas('error');
        $this->assertFalse(app(GoogleApiClient::class)->oauthConnected());
    }

    public function test_disconnect_revokes_and_forgets_the_account(): void
    {
        $this->saveClient();
        $this->connect();
        Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);

        $this->post('/settings/google/disconnect')->assertSessionHas('success');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'revoke'));
        $this->assertFalse(app(GoogleApiClient::class)->configured());
        $this->assertNull(app(SettingsService::class)->get(GoogleApiClient::OAUTH_EMAIL));
    }

    public function test_connecting_without_client_details_explains_what_to_do(): void
    {
        $this->actingAs($this->admin())->get('/settings/google/connect')
            ->assertRedirect(route('settings.index'))
            ->assertSessionHas('error');
    }

    public function test_only_admins_can_connect_google(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)->get('/settings/google/connect')->assertForbidden();
        $this->actingAs($leader)->get('/settings/google/callback?code=x&state=y')->assertForbidden();
        $this->actingAs($leader)->post('/settings/google/disconnect')->assertForbidden();
    }
}
