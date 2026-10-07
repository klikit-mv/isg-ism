<?php

namespace Tests\Feature\Certificates;

use App\Enums\BadgeRequestStatus;
use App\Enums\CertificateType;
use App\Livewire\Attendance\Mark;
use App\Models\Activity;
use App\Models\BadgeRequest;
use App\Models\Certificate;
use App\Models\Group;
use App\Models\LeadershipRecord;
use App\Models\Student;
use App\Services\CertificateGenerationService;
use App\Services\GoogleDriveCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Livewire\Livewire;
use Tests\TestCase;

class CertificateWorkflowTest extends TestCase
{
    use CertificateTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCertificates();
    }

    public function test_badge_request_is_approved_then_generated_as_numbered_pdf(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 27));
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $badge = $this->badge();
        $this->template(CertificateType::Badge);
        $admin = $this->admin();

        $this->actingAs($parent)->post('/badge-requests', ['student' => $student->uuid, 'badge' => $badge->uuid])->assertSessionHas('success');
        $request = BadgeRequest::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^BR-[A-Z0-9]{6}$/', $request->request_id);

        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/generate", ['date_awarded' => '2026-09-27'])->assertSessionHas('error');
        $this->assertSame(0, Certificate::query()->count());

        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/approve", ['note' => 'Well done']);
        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/generate", ['date_awarded' => '2026-09-27']);

        $certificate = Certificate::query()->firstOrFail();
        $this->assertSame('FLHSG-PB-2026-001', $certificate->cert_number);
        $this->assertMatchesRegularExpression('/^C[A-Z0-9]{8}$/', $certificate->cert_id);
        $this->assertSame(BadgeRequestStatus::Generated, $request->fresh()->status);
        $this->assertSame('FLHSG-PB-2026-001', $request->fresh()->certificate_number);
        Storage::disk('certificates')->assertExists("{$student->id}/FLHSG-PB-2026-001.pdf");
        $this->assertStringStartsWith('%PDF', Storage::disk('certificates')->get("{$student->id}/FLHSG-PB-2026-001.pdf"));
    }

    public function test_rejected_request_cannot_be_generated_and_duplicates_are_refused(): void
    {
        $student = Student::factory()->create();
        $badge = $this->badge();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/badge-requests', ['student' => $student->uuid, 'badge' => $badge->uuid]);
        $this->actingAs($admin)->post('/badge-requests', ['student' => $student->uuid, 'badge' => $badge->uuid])->assertSessionHas('error');
        $request = BadgeRequest::query()->firstOrFail();

        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/reject", ['note' => 'Not yet']);
        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/approve")->assertSessionHas('error');
        $this->actingAs($admin)->post("/badge-requests/{$request->uuid}/generate", ['date_awarded' => now()->toDateString()])->assertSessionHas('error');

        $this->actingAs($admin)->post('/badge-requests', ['student' => $student->uuid, 'badge' => $badge->uuid])->assertSessionHas('success');
    }

    public function test_parents_cannot_approve_requests_and_cannot_request_for_others(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $badge = $this->badge();
        $this->actingAs($parent)->post('/badge-requests', ['student' => $student->uuid, 'badge' => $badge->uuid]);
        $request = BadgeRequest::query()->firstOrFail();

        $this->actingAs($parent)->post("/badge-requests/{$request->uuid}/approve")->assertForbidden();
        $this->actingAs($parent)->post('/badge-requests', ['student' => Student::factory()->create()->uuid, 'badge' => $badge->uuid])->assertSessionHas('error');
    }

    public function test_general_certificate_needs_title_and_scout(): void
    {
        $template = $this->template(CertificateType::General);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/certificates', ['title' => 'Best', 'date_awarded' => '2026-01-01', 'template' => $template->uuid])->assertSessionHasErrors('student');
        $this->actingAs($admin)->post('/certificates', ['student' => Student::factory()->create()->uuid, 'date_awarded' => '2026-01-01', 'template' => $template->uuid])->assertSessionHasErrors('title');

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_general_certificate_refuses_mismatched_or_inactive_template(): void
    {
        $student = Student::factory()->create();
        $badgeTemplate = $this->template(CertificateType::Badge);
        $inactive = $this->template(CertificateType::General, ['active' => false]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/certificates', ['student' => $student->uuid, 'title' => 'X', 'date_awarded' => '2026-01-01', 'template' => $badgeTemplate->uuid])->assertSessionHas('error');
        $this->actingAs($admin)->post('/certificates', ['student' => $student->uuid, 'title' => 'X', 'date_awarded' => '2026-01-01', 'template' => $inactive->uuid])->assertSessionHas('error');
        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_bulk_issue_keeps_successes_when_some_fail(): void
    {
        $template = $this->template(CertificateType::General);
        $leader = $this->leader();
        $mine = Student::factory()->count(2)->create();
        $outside = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers(...$mine)->create();

        $this->actingAs($leader)->post('/certificates/bulk-create', [
            'students' => [$mine[0]->id, $mine[1]->id, $outside->id],
            'title' => 'Jamboree 2026', 'date_awarded' => '2026-07-01', 'template' => $template->uuid,
        ])->assertSessionHas('success', '2 certificate(s) issued.')->assertSessionHas('warning');

        $this->assertSame(2, Certificate::query()->count());
    }

    public function test_marking_attendance_issues_activity_certificates_to_present_scouts(): void
    {
        $template = $this->template(CertificateType::General);
        [$present, $late, $absent] = Student::factory()->count(3)->create();
        $activity = Activity::factory()->forAll()->create(['certificate_template_id' => $template->id, 'name' => 'Beach Cleanup']);

        Livewire::actingAs($this->admin())->test(Mark::class, ['activity' => $activity])
            ->set("rows.{$present->id}.status", 'Present')
            ->set("rows.{$late->id}.status", 'Late')
            ->set("rows.{$absent->id}.status", 'Absent')
            ->call('save')
            ->assertSet('error', null);

        $this->assertEqualsCanonicalizing([$present->id, $late->id], Certificate::query()->pluck('student_id')->all());
        $this->assertSame('Beach Cleanup', Certificate::query()->first()->title);

        $this->actingAs($this->admin())->post("/certificates/activities/{$activity->uuid}/issue")->assertSessionHas('success', '0 certificate(s) issued for Beach Cleanup.');
        $this->assertSame(2, Certificate::query()->count());
    }

    public function test_public_verification_valid_and_unknown(): void
    {
        $student = Student::factory()->create(['national_id' => 'A7654321']);
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate($student, 'Swimming Gala', '2026-05-05', $this->template(CertificateType::General), $this->admin());

        $this->get('/certificates/verify?cert_number='.strtolower($certificate->cert_number))
            ->assertOk()->assertSee('data-testid="valid"', false)->assertSee('Swimming Gala')->assertDontSee('A7654321');
        $this->get('/certificates/verify?cert_number=CERT-1999-9999')->assertOk()->assertSee('data-testid="unknown"', false);

        $this->actingAs($this->admin())->get('/certificates/verify?cert_number='.$certificate->cert_number)->assertSee('A7654321');
        auth()->logout();

        $this->get('/certificates/verify/view?cert_number='.$certificate->cert_number)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/certificates/verify/download?cert_number='.$certificate->cert_number)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertDatabaseHas('audit_logs', ['action' => 'certificate.downloaded']);
    }

    public function test_view_and_download_follow_scope(): void
    {
        $student = Student::factory()->create();
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate($student, 'Award', '2026-05-05', $this->template(CertificateType::General), $this->admin());

        $this->actingAs($this->parentOf($student))->get("/certificates/{$certificate->uuid}/download")->assertOk();
        $this->actingAs($this->parentOf(Student::factory()->create()))->get("/certificates/{$certificate->uuid}")->assertForbidden();
        $this->actingAs($this->studentUser($student))->get("/certificates/{$certificate->uuid}/preview")->assertOk();
    }

    public function test_there_is_no_signing_step_or_signature_upload(): void
    {
        $leader = $this->leader();
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'Award', '2026-05-05', $this->template(CertificateType::General), $this->admin());

        $this->actingAs($leader)->post("/certificates/{$certificate->uuid}/sign")->assertNotFound();
        $this->actingAs($leader)->post('/profile/signature')->assertNotFound();
        $this->actingAs($this->admin())->get(route('certificates.show', $certificate))->assertOk()->assertDontSee('Verify and sign');
        $this->actingAs($leader)->get(route('profile.edit'))->assertOk()->assertDontSee('Upload signature');
    }

    public function test_placeholders_are_html_escaped(): void
    {
        $student = Student::factory()->create(['name' => '<script>alert(1)</script>']);
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate($student, 'Award', '2026-05-05', $this->template(CertificateType::General), $this->admin());

        $html = app(CertificateGenerationService::class)->previewHtml($certificate);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('5 May 2026', $html);
    }

    public function test_zip_download_of_selected_certificates(): void
    {
        $admin = $this->admin();
        $template = $this->template(CertificateType::General);
        $a = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'A', '2026-05-05', $template, $admin);
        $b = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'B', '2026-05-05', $template, $admin);

        $response = $this->actingAs($admin)->post('/certificates/bulk-download', ['certificates' => [$a->uuid, $b->uuid]]);

        $response->assertOk()->assertHeader('Content-Type', 'application/zip');
        $zip = new \ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $this->assertSame(2, $zip->numFiles);
        $zip->close();

        $this->actingAs($this->parentOf())->post('/certificates/bulk-download', ['certificates' => [$a->uuid]])->assertForbidden();
    }

    public function test_leadership_certificate_omits_end_date_and_regenerates_under_same_number(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 1));
        $admin = $this->admin();
        $this->template(CertificateType::Leadership);
        $student = Student::factory()->create();

        $this->actingAs($admin)->post('/leadership', [
            'student_id' => $student->id, 'patrol_or_six' => 'Eagle Patrol', 'start_date' => '2026-01-10', 'end_date' => '2026-05-20',
        ])->assertSessionHas('success');
        $record = LeadershipRecord::query()->firstOrFail();
        $this->assertSame(config('scout.organisation'), $record->troop_or_group);

        $this->actingAs($admin)->post("/leadership/{$record->uuid}/generate");
        $certificate = $record->fresh()->certificate;
        $this->assertSame('FLHSG-LEAD-2026-001', $certificate->cert_number);

        $html = app(CertificateGenerationService::class)->previewHtml($certificate);
        $this->assertStringContainsString('10 January 2026', $html);
        $this->assertStringNotContainsString('20 May 2026', $html);

        $this->actingAs($admin)->put("/leadership/{$record->uuid}", ['student_id' => $student->id, 'patrol_or_six' => 'Hawk Patrol', 'start_date' => '2026-02-01']);
        $this->actingAs($admin)->post("/leadership/{$record->uuid}/generate");

        $this->assertSame(1, Certificate::query()->count());
        $this->assertSame('FLHSG-LEAD-2026-001', $record->fresh()->certificate->cert_number);
        $this->assertStringContainsString('Hawk Patrol', app(CertificateGenerationService::class)->previewHtml($record->fresh()->certificate));
    }

    public function test_a_leadership_certificate_is_made_with_the_template_you_choose(): void
    {
        $admin = $this->admin();
        $first = $this->template(CertificateType::Leadership, ['name' => 'Classic']);
        $second = $this->template(CertificateType::Leadership, ['name' => 'Modern']);
        $general = $this->template(CertificateType::General, ['name' => 'Other kind']);
        $record = LeadershipRecord::query()->create(['student_id' => Student::factory()->create()->id, 'patrol_or_six' => 'Eagle Patrol', 'troop_or_group' => 'Group', 'start_date' => '2026-01-10']);

        $this->actingAs($admin)->get(route('leadership.show', $record))->assertOk()->assertSee('Classic')->assertSee('Modern')->assertDontSee('Other kind');

        $this->actingAs($admin)->post("/leadership/{$record->uuid}/generate", ['template' => $general->uuid])->assertSessionHas('error');
        $this->assertNull($record->fresh()->certificate);

        $this->actingAs($admin)->post("/leadership/{$record->uuid}/generate", ['template' => $second->uuid])->assertSessionHas('success');
        $this->assertSame($second->id, $record->fresh()->certificate->template_id);

        $this->actingAs($admin)->post("/leadership/{$record->uuid}/generate", ['template' => $first->uuid]);
        $this->assertSame($first->id, $record->fresh()->certificate->template_id);
    }

    public function test_long_lists_become_a_searchable_dropdown_and_short_ones_stay_plain(): void
    {
        view()->share('errors', new ViewErrorBag);
        $long = Blade::render('<x-form.select name="s" label="Scout" :options="$o" placeholder="Choose a scout"/>', ['o' => array_combine(range(1, 12), array_map(fn ($n) => "Scout {$n}", range(1, 12)))]);
        $short = Blade::render('<x-form.select name="s" :options="$o"/>', ['o' => ['a' => 'A', 'b' => 'B']]);

        $this->assertStringContainsString('Type to search', $long);
        $this->assertStringContainsString('name="s"', $long);
        $this->assertStringNotContainsString('<select', $long);
        $this->assertStringContainsString('<select', $short);
    }

    public function test_leadership_is_managed_by_scoped_staff_and_viewed_by_families(): void
    {
        $leader = $this->leader();
        $mine = Student::factory()->create();
        $other = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers($mine)->create();
        $record = LeadershipRecord::query()->create(['student_id' => $mine->id, 'patrol_or_six' => 'Lion Six', 'troop_or_group' => 'Group', 'start_date' => '2026-01-01']);

        $this->actingAs($leader)->post('/leadership', ['student_id' => $other->id, 'patrol_or_six' => 'X', 'start_date' => '2026-01-01'])->assertForbidden();
        $this->actingAs($leader)->put("/leadership/{$record->uuid}", ['student_id' => $mine->id, 'patrol_or_six' => 'Lion Six', 'start_date' => '2026-01-01', 'end_date' => '2025-01-01'])->assertSessionHasErrors('end_date');

        $parent = $this->parentOf($mine);
        $this->actingAs($parent)->get('/leadership')->assertOk()->assertSee('Lion Six');
        $this->actingAs($parent)->get('/leadership/create')->assertForbidden();
        $this->actingAs($parent)->delete("/leadership/{$record->uuid}")->assertForbidden();
        $this->actingAs($this->parentOf(Student::factory()->create()))->get('/leadership')->assertSee('No records.');
    }

    public function test_template_from_slides_url_and_connection_test(): void
    {
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $this->privateKey()])]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'www.googleapis.com/drive/v3/files/1AbCdEfGhIjKlMnOp/copy*' => Http::response(['id' => 'COPYTEST']),
            'slides.googleapis.com/*' => Http::response([]),
            'www.googleapis.com/drive/v3/files/COPYTEST/export*' => Http::response('%PDF-1.4 test'),
            'www.googleapis.com/drive/v3/files/COPYTEST*' => Http::response([], 204),
            'www.googleapis.com/drive/v3/files/1AbCdEfGhIjKlMnOp*' => Http::response(['id' => '1AbCdEfGhIjKlMnOp', 'name' => 'Badge layout', 'mimeType' => 'application/vnd.google-apps.presentation']),
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/certificate-templates', [
            'name' => 'Slides badge', 'type' => 'badge', 'google_slide' => 'https://docs.google.com/presentation/d/1AbCdEfGhIjKlMnOp/edit#slide=id.p',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('certificate_templates', ['google_slide_id' => '1AbCdEfGhIjKlMnOp']);

        $this->actingAs($admin)->postJson('/certificate-templates/test-slide', ['google_slide' => '1AbCdEfGhIjKlMnOp'])
            ->assertOk()->assertJson(['ok' => true]);
    }

    public function test_slides_export_is_used_when_configured_and_falls_back_on_failure(): void
    {
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $this->privateKey()])]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'token']),
            'www.googleapis.com/drive/v3/files/SLIDES12345/copy*' => Http::response(['id' => 'COPY123456']),
            'slides.googleapis.com/*' => Http::response([]),
            'www.googleapis.com/drive/v3/files/COPY123456/export*' => Http::response('%PDF-1.4 from slides'),
            'www.googleapis.com/drive/v3/files/COPY123456*' => Http::response([], 204),
        ]);
        $template = $this->template(CertificateType::General, ['google_slide_id' => 'SLIDES12345']);
        $student = Student::factory()->create();

        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate($student, 'Award', '2026-05-05', $template, $this->admin());

        $this->assertSame('%PDF-1.4 from slides', app(GoogleDriveCertificateService::class)->contents($certificate->path));
        Http::assertSent(fn ($request) => str_contains($request->url(), 'batchUpdate') && str_contains($request->body(), 'Award'));
    }

    public function test_used_template_and_badge_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $template = $this->template(CertificateType::General);
        app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'Award', '2026-05-05', $template, $admin);
        $badge = $this->badge();
        $this->actingAs($admin)->post('/badge-requests', ['student' => Student::factory()->create()->uuid, 'badge' => $badge->uuid]);

        $this->actingAs($admin)->delete("/certificate-templates/{$template->uuid}")->assertSessionHas('error');
        $this->actingAs($admin)->delete("/badges/{$badge->uuid}")->assertSessionHas('error');
        $this->assertModelExists($template);
        $this->assertModelExists($badge);
    }

    public function test_linking_a_template_to_an_activity_moves_the_link(): void
    {
        $admin = $this->admin();
        $first = Activity::factory()->forAll()->create();
        $second = Activity::factory()->forAll()->create();
        $template = $this->template(CertificateType::General);

        $this->actingAs($admin)->put("/certificate-templates/{$template->uuid}", ['name' => 'T', 'type' => 'general', 'activity_id' => $first->id]);
        $this->assertSame($template->id, $first->fresh()->certificate_template_id);

        $this->actingAs($admin)->put("/certificate-templates/{$template->uuid}", ['name' => 'T', 'type' => 'general', 'activity_id' => $second->id]);
        $this->assertNull($first->fresh()->certificate_template_id);
        $this->assertSame($template->id, $second->fresh()->certificate_template_id);
    }

    public function test_leaders_view_but_cannot_manage_templates_or_badges(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)->get('/certificate-templates')->assertOk();
        $this->actingAs($leader)->get('/badges')->assertOk();
        $this->actingAs($leader)->post('/certificate-templates', ['name' => 'X', 'type' => 'badge'])->assertForbidden();
        $this->actingAs($leader)->post('/badges', ['name' => 'X', 'code' => 'X'])->assertForbidden();
        $this->actingAs($this->parentOf())->get('/badges')->assertForbidden();
    }

    private function privateKey(): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);

        return $pem;
    }

    public function test_the_certificate_page_shows_the_generated_pdf_not_the_built_in_layout(): void
    {
        $admin = $this->admin();
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate(Student::factory()->create(), 'Swimming Gala', '2026-05-05', $this->template(CertificateType::General), $admin);
        $stored = app(GoogleDriveCertificateService::class)->put($certificate->student, $certificate->cert_number, "%PDF-1.4 from google slides\n", $certificate->path);
        $certificate->forceFill(['path' => $stored])->save();

        $response = $this->actingAs($admin)->get(route('certificates.preview', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('from google slides', $response->getContent());
        $this->actingAs($admin)->get(route('certificates.show', $certificate))->assertOk()->assertDontSee('sandbox', false);
    }
}
