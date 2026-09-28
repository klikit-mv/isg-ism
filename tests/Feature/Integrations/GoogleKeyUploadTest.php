<?php

namespace Tests\Feature\Integrations;

use App\Models\Setting;
use App\Services\Google\GoogleApiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleKeyUploadTest extends TestCase
{
    use RefreshDatabase;

    private function key(array $overrides = []): string
    {
        $pkey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pkey, $pem);

        return (string) json_encode($overrides + [
            'type' => 'service_account',
            'project_id' => 'scout-portal',
            'client_email' => 'scout-bot@scout-portal.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]);
    }

    private function upload(string $json): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('service-account.json', $json);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['default_class_fee' => '50', 'proof_max_kb' => 10240, 'shop_enabled' => '1'], $overrides);
    }

    public function test_admin_connects_google_by_uploading_the_key_file(): void
    {
        config(['services.google.service_account_json' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings', $this->payload(['google_service_account' => $this->upload($this->key())]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $client = app(GoogleApiClient::class);
        $this->assertTrue($client->configured());
        $this->assertSame('settings', $client->source());
        $this->assertSame('scout-bot@scout-portal.iam.gserviceaccount.com', $client->clientEmail());
        $this->assertStringNotContainsString('PRIVATE KEY', (string) Setting::query()->where('key', GoogleApiClient::SETTING_KEY)->value('value'));

        $this->actingAs($admin)->get('/settings')
            ->assertSee('data-testid="google-connected"', false)
            ->assertSee('scout-bot@scout-portal.iam.gserviceaccount.com')
            ->assertDontSee('PRIVATE KEY');
    }

    public function test_the_uploaded_key_is_used_to_set_up_drive_folders_in_the_same_save(): void
    {
        config(['services.google.service_account_json' => null]);
        $created = ['STUDENTSFOLDER1', 'SHOPFOLDER00001', 'BADGESFOLDER001', 'SIGNATURESFLDR1'];
        Http::fake(function (Request $request) use (&$created) {
            return match (true) {
                str_contains($request->url(), 'oauth2.googleapis.com') => Http::response(['access_token' => 'token']),
                str_contains($request->url(), 'files/ROOTFOLDER123') => Http::response(['id' => 'ROOTFOLDER123', 'mimeType' => 'application/vnd.google-apps.folder', 'trashed' => false]),
                $request->method() === 'GET' => Http::response(['files' => []]),
                default => Http::response(['id' => array_shift($created)]),
            };
        });

        $this->actingAs($this->admin())->post('/settings', $this->payload([
            'google_service_account' => $this->upload($this->key()),
            'google_drive_folder' => 'https://drive.google.com/drive/folders/ROOTFOLDER123',
        ]))->assertSessionHas('success');

        $this->assertStringContainsString('Drive is ready', session('success'));
    }

    public function test_invalid_key_files_are_explained(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings', $this->payload(['google_service_account' => $this->upload('not json')]))
            ->assertSessionHasErrors('google_service_account');
        $this->actingAs($admin)->post('/settings', $this->payload(['google_service_account' => $this->upload($this->key(['type' => 'authorized_user']))]))
            ->assertSessionHasErrors(['google_service_account' => 'That JSON is not a service account key. It must contain "type": "service_account".']);

        $this->assertNull(Setting::query()->where('key', GoogleApiClient::SETTING_KEY)->value('value'));
    }

    public function test_admin_removes_the_saved_key(): void
    {
        config(['services.google.service_account_json' => null]);
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings', $this->payload(['google_service_account' => $this->upload($this->key())]));

        $this->actingAs($admin)->post('/settings', $this->payload(['remove_google_service_account' => '1']))
            ->assertSessionHas('success');

        $this->assertFalse(app(GoogleApiClient::class)->configured());
    }

    public function test_leaders_cannot_upload_a_key(): void
    {
        $this->actingAs($this->leader())->post('/settings', $this->payload(['google_service_account' => $this->upload($this->key())]))
            ->assertForbidden();
    }
}
