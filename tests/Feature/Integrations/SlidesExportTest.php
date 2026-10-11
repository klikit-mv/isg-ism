<?php

namespace Tests\Feature\Integrations;

use App\Models\Group;
use App\Services\Google\GoogleSlideExporter;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlidesExportTest extends TestCase
{
    use RefreshDatabase;

    private function connect(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Cache::put('google.access_token.'.md5('bot@example.iam.gserviceaccount.com'), 'token', 600);
        app(SettingsService::class)->set('google_drive_certificates_folder_id', 'certfolder');
    }

    public function test_a_slides_template_is_copied_into_the_certificates_folder_filled_in_and_exported(): void
    {
        $this->connect();
        Http::fake([
            'www.googleapis.com/drive/v3/files/pres1/copy*' => Http::response(['id' => 'copy1']),
            'slides.googleapis.com/*' => Http::response([]),
            'www.googleapis.com/drive/v3/files/copy1/export*' => Http::response('%PDF-1.4 fake'),
            'www.googleapis.com/drive/v3/files/copy1*' => Http::response([], 204),
        ]);

        $exporter = app(GoogleSlideExporter::class);
        $pdf = $exporter->exportPdf('pres1', ['{{name}}' => 'Aishath']);

        $this->assertSame('%PDF-1.4 fake', $pdf);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/pres1/copy') && ($r['parents'] ?? null) === ['certfolder']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'copy1:batchUpdate') && $r['requests'][0]['replaceAllText']['replaceText'] === 'Aishath');
    }

    public function test_a_refused_copy_is_explained_instead_of_silently_falling_back(): void
    {
        $this->connect();
        Http::fake(['www.googleapis.com/drive/v3/files/pres1/copy*' => Http::response(['error' => ['message' => 'quota', 'errors' => [['reason' => 'storageQuotaExceeded']]]], 403)]);

        $exporter = app(GoogleSlideExporter::class);

        $this->assertNull($exporter->exportPdf('pres1', []));
        $this->assertStringContainsString('no storage', $exporter->lastError());
    }

    public function test_activity_form_offers_only_active_groups_and_all_sections(): void
    {
        $active = Group::factory()->create(['name' => 'Active Eagles', 'status' => 'Active']);
        $inactive = Group::factory()->create(['name' => 'Retired Owls', 'status' => 'Inactive']);

        $this->actingAs($this->admin())->get('/activities')->assertOk()
            ->assertSee('Active Eagles')->assertDontSee('Retired Owls')
            ->assertSee('Pre Cub')->assertSee('Rover');
    }
}
