<?php

namespace Tests\Feature\Certificates;

use App\Enums\CertificateType;
use App\Models\Certificate;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExternalCertificateTest extends TestCase
{
    use CertificateTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCertificates();
        Storage::fake('local');
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_many_pdfs_are_matched_to_scouts_by_file_name_then_saved(): void
    {
        $admin = $this->admin();
        $first = Student::factory()->create(['national_id' => 'A1234567']);
        $second = Student::factory()->create(['name' => 'Ibrahim Naseem']);

        $review = $this->actingAs($admin)->post(route('certificates.external.stage'), [
            'issuer' => 'SAM',
            'date_awarded' => '2026-11-01',
            'files' => [$this->pdf('A1234567 first aid.pdf'), $this->pdf('ibrahim_naseem_camp.pdf'), $this->pdf('unknown.pdf')],
        ])->assertOk()->assertSee('data-testid="external-review"', false);

        $this->assertStringContainsString('value="'.$first->id.'"', $review->getContent());
        $this->assertStringContainsString('value="'.$second->id.'"', $review->getContent());

        preg_match('/name="token" value="([^"]+)"/', $review->getContent(), $token);

        $this->actingAs($admin)->post(route('certificates.external.store'), [
            'token' => $token[1],
            'issuer' => 'SAM',
            'date_awarded' => '2026-11-01',
            'rows' => [
                ['key' => 'f0', 'student_id' => $first->id, 'title' => 'First aid'],
                ['key' => 'f1', 'student_id' => $second->id, 'title' => 'Camp'],
                ['key' => 'f2', 'student_id' => null, 'title' => 'Unknown'],
            ],
        ])->assertRedirect(route('certificates.index'))->assertSessionHas('success', '2 certificate(s) uploaded.')->assertSessionHas('warning');

        $certificate = Certificate::query()->where('student_id', $first->id)->firstOrFail();
        $this->assertSame(CertificateType::External, $certificate->type);
        $this->assertSame('SAM', $certificate->issuer);
        $this->assertStringContainsString('-EXT-', $certificate->cert_number);
        $this->assertSame('First aid (SAM)', $certificate->displayTitle());
        $this->assertSame(2, Certificate::query()->count());
    }

    public function test_the_scout_sees_it_with_the_other_certificates_and_it_is_never_regenerated(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $user = $this->studentUser($student);

        $review = $this->actingAs($admin)->post(route('certificates.external.stage'), ['issuer' => 'SAM', 'date_awarded' => '2026-11-01', 'files' => [$this->pdf('x.pdf')]]);
        preg_match('/name="token" value="([^"]+)"/', $review->getContent(), $token);
        $this->actingAs($admin)->post(route('certificates.external.store'), ['token' => $token[1], 'issuer' => 'SAM', 'date_awarded' => '2026-11-01', 'rows' => [['key' => 'f0', 'student_id' => $student->id, 'title' => 'Water safety']]]);

        $certificate = Certificate::query()->firstOrFail();

        $this->actingAs($user)->get(route('self.certificates'))->assertOk()->assertSee('Water safety (SAM)');
        $this->actingAs($user)->get(route('certificates.download', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($admin)->post(route('certificates.regenerate', $certificate))->assertSessionHas('error');
    }

    public function test_files_that_are_not_pdfs_are_refused(): void
    {
        $this->actingAs($this->admin())->post(route('certificates.external.stage'), [
            'issuer' => 'SAM',
            'date_awarded' => '2026-11-01',
            'files' => [UploadedFile::fake()->createWithContent('fake.pdf', 'not a pdf')],
        ])->assertRedirect();

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_only_an_admin_can_delete_a_certificate(): void
    {
        $student = Student::factory()->create();
        $certificate = Certificate::query()->create([
            'cert_id' => 'CDEL00001', 'type' => CertificateType::External, 'student_id' => $student->id, 'student_name' => $student->name,
            'title' => 'Old', 'cert_number' => 'EXT-DEL-001', 'date_awarded' => '2026-11-01', 'status' => 'verified',
        ]);

        $this->actingAs($this->leader())->delete(route('certificates.destroy', $certificate))->assertForbidden();
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id]);

        $this->actingAs($this->admin())->get(route('certificates.show', $certificate))->assertSee('Delete');
        $this->actingAs($this->admin())->delete(route('certificates.destroy', $certificate))->assertRedirect(route('certificates.index'));
        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }
}
