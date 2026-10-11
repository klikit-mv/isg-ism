<?php

namespace Database\Seeders;

use App\Enums\CertificateType;
use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Models\Activity;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\User;
use App\Services\AnnualFeeService;
use App\Services\AttendanceService;
use App\Services\CertificateService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Support\CertificateTemplateDefaults;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development and tests. Never runs in production.
 */
class DemoSeeder extends Seeder
{
    /**
     * PIN for every sample account unless SCOUT_ADMIN_PIN is set.
     */
    public const SAMPLE_PIN = '123456';

    /**
     * The sample sign-ins shown on the local sign-in page.
     *
     * @return list<array{role: string, national_id: string}>
     */
    public static function sampleAccounts(): array
    {
        return [
            ['role' => 'Admin', 'national_id' => strtoupper((string) config('scout.admin.national_id'))],
            ['role' => 'Leader', 'national_id' => 'A100001'],
            ['role' => 'Leader (treasurer)', 'national_id' => 'A100002'],
            ['role' => 'Parent', 'national_id' => 'A100003'],
            ['role' => 'Scout', 'national_id' => 'A200001'],
        ];
    }

    public static function samplePin(): string
    {
        return (string) (config('scout.admin.pin') ?: self::SAMPLE_PIN);
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $pin = self::samplePin();
        $admin = User::query()->firstOrCreate(
            ['national_id' => strtoupper((string) config('scout.admin.national_id'))],
            ['name' => config('scout.admin.name'), 'email' => 'admin@example.com', 'password' => $pin, 'status' => 'active', 'verified_at' => now()],
        );
        $admin->assignRole(Role::Admin);
        $this->command?->info("Sample admin: {$admin->national_id} / PIN {$pin}");

        $leader = User::factory()->leader()->create(['name' => 'Hassan Leader', 'national_id' => 'A100001', 'password' => $pin]);
        $treasurer = User::factory()->leader()->withPermissions(Permission::VerifyPayments, Permission::ManageShop, Permission::ProcessDelivery, Permission::ManageFees)
            ->create(['name' => 'Mariyam Treasurer', 'national_id' => 'A100002', 'password' => $pin]);

        $sections = [ScoutSection::PreCub, ScoutSection::CubScout, ScoutSection::Scout, ScoutSection::Rover];
        $students = collect();

        foreach ($sections as $section) {
            foreach (range(1, 4) as $i) {
                $student = $section === ScoutSection::Scout && $i === 1
                    ? Student::factory()->section($section)->create(['name' => 'Ibrahim Scout', 'national_id' => 'A200001'])
                    : Student::factory()->section($section)->create();
                User::factory()->forStudent($student)->create(['password' => $pin]);
                $students->push($student);
            }
        }

        Student::factory()->pending()->create(['name' => 'Waiting Scout']);

        $eagle = Group::factory()->ledBy($leader)->withMembers(...$students->where('section', ScoutSection::Scout)->all())->create(['name' => 'Eagle Patrol', 'owner_id' => $admin->id]);
        $lion = Group::factory()->ledBy($treasurer)->withMembers(...$students->where('section', ScoutSection::CubScout)->all())->create(['name' => 'Lion Six', 'type' => 'Six', 'owner_id' => $admin->id]);
        $eagle->assistantLeaders()->attach($students->firstWhere('section', ScoutSection::Rover)->id);

        $parent = User::factory()->parentRole()->create(['name' => 'Aminath Parent', 'national_id' => 'A100003', 'password' => $pin]);
        $parent->parentLinks()->create(['student_id' => $students->firstWhere('section', ScoutSection::Scout)->id, 'status' => 'approved']);
        $parent->parentLinks()->create(['student_id' => $students->firstWhere('section', ScoutSection::CubScout)->id, 'status' => 'approved']);

        $templates = [];

        foreach (CertificateType::cases() as $type) {
            $templates[$type->value] = CertificateTemplate::query()->create([
                'template_id' => 'TPL-'.strtoupper($type->value === 'leadership' ? 'LEADER' : str_pad($type->value, 6, 'X')),
                'name' => ucfirst($type->value).' certificate',
                'type' => $type,
                'google_slide_id' => 'local-'.$type->value,
                'template_content' => CertificateTemplateDefaults::for($type),
                'active' => true,
            ]);
        }

        $camping = Badge::query()->create(['badge_id' => 'BCAMP1', 'name' => 'Camping', 'code' => 'CAMP', 'section' => ScoutSection::Scout, 'category' => 'proficiency']);
        Badge::query()->create(['badge_id' => 'BFIRST', 'name' => 'First Aid', 'code' => 'FIRSTAID', 'section' => ScoutSection::Scout, 'category' => 'proficiency']);

        $meeting = Activity::factory()->forGroups($eagle, $lion)->charged('20.00')->create(['name' => 'Weekly Meeting', 'date' => now()->subWeek()->toDateString(), 'created_by' => $admin->id]);
        $cleanup = Activity::factory()->forAll()->create(['name' => 'Beach Cleanup', 'date' => now()->subDays(3)->toDateString(), 'certificate_template_id' => $templates['general']->id, 'created_by' => $admin->id]);
        $templates['general']->update(['activity_id' => $cleanup->id]);

        $marks = [];

        foreach ($students->whereIn('section', [ScoutSection::Scout, ScoutSection::CubScout])->values() as $index => $student) {
            $marks[$student->id] = ['status' => ['Present', 'Late', 'Absent', 'Excused'][$index % 4], 'payment' => $index % 2 === 0 ? '10' : '0'];
        }

        app(AttendanceService::class)->mark($meeting, $admin, $marks);
        app(AttendanceService::class)->mark($cleanup, $admin, [$students->first()->id => ['status' => 'Present']]);
        app(CertificateService::class)->issueForActivityAttendance($cleanup, $admin);

        $fee = ClassFee::query()->where('status', 'Pending')->first();

        if ($fee) {
            app(PaymentService::class)->submit($fee, $admin, '20.00', PaymentMethod::Cash);
        }

        $year = app(AnnualFeeService::class)->createYear((int) now()->format('Y'), '150.00', $admin);
        app(AnnualFeeService::class)->generate($year, $students->take(6)->map(fn ($s) => ['type' => 'Student', 'id' => $s->id])->all(), $admin);

        $scarf = ShopItem::query()->create(['name' => 'Group scarf', 'description' => 'Purple and green group scarf.', 'price' => '85.00', 'stock_qty' => 40, 'status' => 'Active', 'created_by' => $admin->id]);
        ShopItem::query()->create(['name' => 'Woggle', 'description' => 'Leather woggle.', 'price' => '25.00', 'stock_qty' => 60, 'status' => 'Active', 'created_by' => $admin->id]);
        $purchase = app(PurchaseService::class)->create($scarf, $students->first(), 1, $admin);
        app(PaymentService::class)->submit($purchase, $admin, '85.00', PaymentMethod::Cash);

        $request = app(CertificateService::class)->requestBadge($students->firstWhere('section', ScoutSection::Scout), $camping, $leader);
        app(CertificateService::class)->approve($request, $admin, 'Great camp');

    }
}
