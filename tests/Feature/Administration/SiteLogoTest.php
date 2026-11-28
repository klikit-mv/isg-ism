<?php

namespace Tests\Feature\Administration;

use App\Enums\CertificateType;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Services\CertificateGenerationService;
use App\Services\SettingsService;
use App\Support\CertificateTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['default_class_fee' => '50', 'proof_max_kb' => 10240, 'shop_enabled' => '1'], $overrides);
    }

    public function test_admin_uploads_a_logo_shown_in_the_header_and_sign_in_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 200, 200)]))
            ->assertSessionHas('success');

        $path = app(SettingsService::class)->logoPath();
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($admin)->get('/dashboard')->assertSee('data-testid="site-logo"', false)->assertSee('src="/branding/logo?v=', false);
        auth()->logout();
        $this->get('/login')->assertSee('data-testid="site-logo"', false)->assertSee('rel="icon"', false)->assertSee('rel="apple-touch-icon"', false);
    }

    /**
     * The logo is served by the app, so it loads without `storage:link`
     * and regardless of APP_URL (a broken image was shown before).
     */
    public function test_logo_url_serves_the_image_to_guests(): void
    {
        $this->actingAs($this->admin())->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 64, 64)]));
        auth()->logout();

        $url = app(SettingsService::class)->logoUrl();
        $this->assertStringStartsWith('/branding/logo?v=', $url);

        $response = $this->get($url);
        $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame(Storage::disk('public')->get(app(SettingsService::class)->logoPath()), $response->streamedContent());
    }

    public function test_logo_url_is_not_found_without_an_upload(): void
    {
        $this->get('/branding/logo')->assertNotFound();
    }

    public function test_replacing_the_logo_deletes_the_old_file_and_removing_restores_the_default(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('one.png')]));
        $first = app(SettingsService::class)->logoPath();

        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('two.jpg')]));
        $second = app(SettingsService::class)->logoPath();
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $this->actingAs($admin)->post('/settings', $this->payload(['remove_logo' => '1']))->assertSessionHas('success');
        $this->assertNull(app(SettingsService::class)->logoPath());
        Storage::disk('public')->assertMissing($second);
        $this->actingAs($admin)->get('/dashboard')->assertDontSee('data-testid="site-logo"', false);
    }

    /**
     * On some Windows setups (Laravel Herd) realpath() of the PHP upload temp
     * file is empty, which crashed saving with "Path must not be empty".
     */
    public function test_logo_saves_when_the_upload_has_no_real_path(): void
    {
        $fake = UploadedFile::fake()->image('logo.png', 120, 120);
        $file = new class($fake->getPathname(), 'logo.png', 'image/png', null, true) extends UploadedFile
        {
            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $this->actingAs($this->admin())->post('/settings', $this->payload(['logo' => $file]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Storage::disk('public')->assertExists(app(SettingsService::class)->logoPath());
    }

    public function test_oversized_logo_is_refused_before_anything_is_saved(): void
    {
        $this->actingAs($this->admin())->post('/settings', $this->payload([
            'footer_text' => 'Should not be saved',
            'logo' => UploadedFile::fake()->image('huge.png', 2500, 100),
        ]))->assertSessionHasErrors(['logo' => 'The logo can be at most 2000 × 2000 pixels.']);

        $this->assertNull(app(SettingsService::class)->logoPath());
        $this->assertNotSame('Should not be saved', app(SettingsService::class)->footerText());
    }

    public function test_only_png_or_jpeg_images_are_accepted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')]))
            ->assertSessionHasErrors('logo');
        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->create('logo.pdf', 5, 'application/pdf')]))
            ->assertSessionHasErrors('logo');

        $this->assertNull(app(SettingsService::class)->logoPath());
    }

    public function test_only_admins_can_change_the_logo(): void
    {
        $this->actingAs($this->leader())->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png')]))
            ->assertForbidden();
    }

    public function test_certificates_use_the_uploaded_logo(): void
    {
        Storage::fake('certificates');
        $admin = $this->admin();
        $this->actingAs($admin)->post('/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 64, 64)]));
        $template = CertificateTemplate::query()->create([
            'template_id' => 'TPL-LOGO01', 'name' => 'General', 'type' => CertificateType::General,
            'google_slide_id' => 'local-general', 'template_content' => CertificateTemplateDefaults::for(CertificateType::General), 'active' => true,
        ]);

        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'Award', '2026-05-05', $template, $admin);

        $this->assertStringStartsWith('data:image/png;base64,', app(CertificateGenerationService::class)->valuesFor($certificate)['logo']);
    }
}
