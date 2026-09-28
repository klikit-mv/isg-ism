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

        $this->actingAs($admin)->get('/dashboard')->assertSee('data-testid="site-logo"', false)->assertSee(basename($path));
        auth()->logout();
        $this->get('/login')->assertSee('data-testid="site-logo"', false)->assertSee('rel="icon"', false);
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
