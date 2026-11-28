<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateType;
use App\Services\Google\GoogleSlideExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TemplatePreviewTest extends TestCase
{
    use CertificateTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCertificates();
    }

    public function test_a_google_slides_template_previews_the_slides_pdf_not_the_built_in_layout(): void
    {
        $slides = Mockery::mock(GoogleSlideExporter::class);
        $slides->shouldReceive('exportPdf')->once()->andReturn("%PDF-1.4\nfrom slides");
        $this->app->instance(GoogleSlideExporter::class, $slides);
        $template = $this->template(CertificateType::General, ['google_slide_id' => 'realSlideId123']);

        $this->actingAs($this->admin())->get(route('certificate-templates.preview', $template))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_when_no_filled_pdf_can_be_made_the_google_slide_itself_is_shown_never_the_built_in_layout(): void
    {
        $slides = Mockery::mock(GoogleSlideExporter::class);
        $slides->shouldReceive('exportPdf')->once()->andReturn(null);
        $slides->shouldReceive('lastError')->andReturn('Google is not connected.');
        $this->app->instance(GoogleSlideExporter::class, $slides);
        $template = $this->template(CertificateType::General, ['google_slide_id' => 'realSlideId123']);

        $this->actingAs($this->admin())->get(route('certificate-templates.preview', $template))
            ->assertOk()
            ->assertSee('https://docs.google.com/presentation/d/realSlideId123/embed', false)
            ->assertSee('Google is not connected.')
            ->assertDontSee('Certificate no.');
    }

    public function test_a_built_in_template_previews_the_built_in_layout(): void
    {
        $template = $this->template(CertificateType::General);

        $this->actingAs($this->admin())->get(route('certificate-templates.preview', $template))
            ->assertOk()
            ->assertSee('Certificate no.');
    }
}
