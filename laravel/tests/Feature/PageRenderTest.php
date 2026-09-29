<?php

namespace Tests\Feature;

use App\Enums\CertificateType;
use App\Enums\PaymentMethod;
use App\Models\Activity;
use App\Models\AnnualFeeYear;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\LeadershipRecord;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use App\Services\AttendanceService;
use App\Services\CertificateGenerationService;
use App\Services\CertificateService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Support\CertificateTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Every screen renders for the people who can open it.
 */
class PageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $admin = $this->admin();
        $student = Student::factory()->create();
        $this->parentOf($student);
        $group = Group::factory()->withMembers($student)->create();
        $user = User::factory()->leader()->create();
        $admin->notify(new ScoutAlert('Hello', 'World', url('/dashboard')));
        $notification = $admin->notifications()->first();

        $pages = [
            '/dashboard', '/profile', '/notifications', "/notifications/{$notification->id}",
            '/users', '/users/create', "/users/{$user->uuid}/edit", '/parent-links',
            '/students', '/students/create', "/students/{$student->uuid}", "/students/{$student->uuid}/edit",
            "/students/{$student->uuid}/certificates", "/students/{$student->uuid}/badge-requests", "/students/{$student->uuid}/leadership",
            '/students/promote', '/students/promote?from=Scout', '/parent-registrations', '/groups', "/groups/{$group->uuid}",
            '/settings', '/audit-logs', '/import', '/students/import', '/reports',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }
    }

    public function test_operations_finance_and_shop_pages_render(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $activity = Activity::factory()->forAll()->charged()->create();
        app(AttendanceService::class)->mark($activity, $admin, [$student->id => ['status' => 'Present']]);
        $fee = ClassFee::query()->firstOrFail();
        app(PaymentService::class)->submit($fee, $parent, '10', PaymentMethod::Online, UploadedFile::fake()->image('p.png'));
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100', 'status' => 'Active']);
        $item = ShopItem::query()->create(['name' => 'Scarf', 'price' => '10', 'stock_qty' => 3, 'status' => 'Active']);
        app(PurchaseService::class)->create($item, $student, 1, $admin);

        $pages = [
            '/activities', "/activities/{$activity->uuid}/edit", '/attendance', "/attendance/{$activity->uuid}/mark",
            '/rover-attendance', "/rover-attendance/{$activity->uuid}/mark",
            '/class-fees', '/annual-fees', '/annual-fees/years', "/annual-fees/years/{$year->uuid}/generate",
            '/payments', '/payment-verification', '/shop', '/purchases',
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        foreach (['/class-fees', '/annual-fees', '/payments', '/shop', '/purchases'] as $page) {
            $this->actingAs($parent)->get($page)->assertOk();
        }

        $this->actingAs($this->studentUser($student))->get('/me/fees')->assertOk();
    }

    public function test_certificate_pages_render(): void
    {
        Storage::fake('certificates');
        $admin = $this->admin();
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $template = CertificateTemplate::query()->create(['template_id' => 'TPL-AAAAAA', 'name' => 'General', 'type' => 'general', 'google_slide_id' => 'local-general', 'template_content' => CertificateTemplateDefaults::for(CertificateType::General), 'active' => true]);
        $certificate = app(CertificateGenerationService::class)->generateGeneralCertificate($student, 'Award', '2026-01-01', $template, $admin);
        $badge = Badge::query()->create(['badge_id' => 'BAAAAA', 'name' => 'Camping', 'code' => 'CAMP', 'section' => 'Scout']);
        $request = app(CertificateService::class)->requestBadge($student, $badge, $admin);
        $record = LeadershipRecord::query()->create(['student_id' => $student->id, 'patrol_or_six' => 'Eagle', 'troop_or_group' => 'Group', 'start_date' => '2026-01-01']);
        Activity::factory()->forAll()->create(['certificate_template_id' => $template->id]);

        $pages = [
            '/certificates', '/certificates/create', '/certificates/bulk-create', "/certificates/{$certificate->uuid}", "/certificates/{$certificate->uuid}/preview",
            '/badges', '/badge-requests', '/badge-requests/create', "/badge-requests/{$request->uuid}",
            '/certificate-templates', "/certificate-templates/{$template->uuid}/preview",
            '/leadership', '/leadership/create', "/leadership/{$record->uuid}", "/leadership/{$record->uuid}/edit",
            "/students/{$student->uuid}/certificates", "/students/{$student->uuid}/badge-requests", "/students/{$student->uuid}/leadership",
        ];

        foreach ($pages as $page) {
            $this->actingAs($admin)->get($page)->assertOk();
        }

        foreach (['/certificates', '/badge-requests', '/badge-requests/create', "/badge-requests/{$request->uuid}", '/leadership', "/certificates/{$certificate->uuid}"] as $page) {
            $this->actingAs($parent)->get($page)->assertOk();
        }

        $this->get('/certificates/verify')->assertOk();
    }

    public function test_family_and_self_pages_render(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $scout = $this->studentUser($student);

        foreach (['/family', '/family/attendance', "/family/students/{$student->uuid}", "/family/students/{$student->uuid}/certificates"] as $page) {
            $this->actingAs($parent)->get($page)->assertOk();
        }

        foreach (['/me', '/me/certificates', '/me/badge-requests', '/me/leadership', '/me/attendance'] as $page) {
            $this->actingAs($scout)->get($page)->assertOk();
        }
    }

    public function test_guest_pages_render(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
        $this->get('/register/parent')->assertOk();
    }
}
