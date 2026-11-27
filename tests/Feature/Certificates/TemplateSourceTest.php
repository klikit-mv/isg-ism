<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateType;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Services\CertificateGenerationService;
use App\Services\Google\GoogleSlideExporter;
use App\Services\GoogleDriveCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateSourceTest extends TestCase
{
    use CertificateTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCertificates();
        Storage::fake('local');
    }

    public function test_a_google_slides_template_previews_the_real_pdf_not_the_built_in_layout(): void
    {
        $slides = \Mockery::mock(GoogleSlideExporter::class);
        $slides->shouldReceive('exportPdf')->once()->andReturn("%PDF-1.4\nfrom slides");
        $this->app->instance(GoogleSlideExporter::class, $slides);
        $template = $this->template(CertificateType::General, ['google_slide_id' => 'realSlideId123']);

        $this->actingAs($this->admin())->get(route('certificate-templates.preview', $template))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_failed_slides_preview_says_why_instead_of_showing_the_built_in_layout(): void
    {
        $slides = \Mockery::mock(GoogleSlideExporter::class);
        $slides->shouldReceive('exportPdf')->once()->andReturn(null);
        $slides->shouldReceive('lastError')->andReturn('Google is not connected.');
        $this->app->instance(GoogleSlideExporter::class, $slides);
        $template = $this->template(CertificateType::General, ['google_slide_id' => 'realSlideId123']);

        $this->actingAs($this->admin())->get(route('certificate-templates.preview', $template))
            ->assertStatus(502)
            ->assertSee('could not be made')
            ->assertSee('Google is not connected.')
            ->assertDontSee('Certificate no.');
    }

    public function test_a_canva_design_is_attached_as_an_image_and_previewed_with_text_over_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('certificate-templates.store'), [
            'name' => 'Canva award', 'type' => 'general', 'source' => 'canva', 'canva_url' => 'https://www.canva.com/design/DAF123/edit',
            'background' => UploadedFile::fake()->image('design.png', 1000, 700), 'text_top' => 40, 'text_color' => '#112233', 'active' => 1,
        ])->assertSessionHasNoErrors();

        $template = CertificateTemplate::query()->where('name', 'Canva award')->firstOrFail();
        $this->assertSame('canva', $template->source());
        $this->assertSame('https://www.canva.com/design/DAF123/edit', $template->canva_url);
        Storage::disk('local')->assertExists($template->background_path);

        $this->actingAs($admin)->get(route('certificate-templates.preview', $template))
            ->assertOk()
            ->assertSee('data:image/png;base64', false)
            ->assertSee('Aishath Sample')
            ->assertSee('top: 40%', false);

        $this->actingAs($admin)->get(route('certificate-templates.index'))->assertSee('Open in Canva');
    }

    public function test_a_canva_template_needs_an_exported_image_and_switching_back_removes_it(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('certificate-templates.store'), ['name' => 'No image', 'type' => 'general', 'source' => 'canva', 'active' => 1])->assertSessionHas('error');
        $this->assertDatabaseMissing('certificate_templates', ['name' => 'No image']);

        $this->actingAs($admin)->post(route('certificate-templates.store'), ['name' => 'Pic', 'type' => 'general', 'source' => 'canva', 'background' => UploadedFile::fake()->image('d.jpg'), 'active' => 1]);
        $template = CertificateTemplate::query()->where('name', 'Pic')->firstOrFail();
        $path = $template->background_path;

        $this->actingAs($admin)->put(route('certificate-templates.update', $template), ['name' => 'Pic', 'type' => 'general', 'source' => 'builtin', 'active' => 1])->assertSessionHasNoErrors();

        $this->assertSame('builtin', $template->fresh()->source());
        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_certificate_made_from_a_canva_template_is_a_pdf(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('certificate-templates.store'), ['name' => 'Canva pdf', 'type' => 'general', 'source' => 'canva', 'background' => UploadedFile::fake()->image('d.png', 800, 560), 'active' => 1]);
        $template = CertificateTemplate::query()->where('name', 'Canva pdf')->firstOrFail();

        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'Award', '2026-05-05', $template, $admin);

        $this->assertStringStartsWith('%PDF', (string) app(GoogleDriveCertificateService::class)->contents($certificate->path));
    }
}
