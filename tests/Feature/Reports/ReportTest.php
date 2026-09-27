<?php

namespace Tests\Feature\Reports;

use App\Models\Activity;
use App\Models\Group;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function seedFees(int $count, string $fee = '10.00'): Activity
    {
        $students = Student::factory()->count($count)->create();
        $activity = Activity::factory()->forAll()->charged($fee)->create(['name' => 'Charged Meeting']);
        $marks = [];

        foreach ($students as $index => $student) {
            $marks[$student->id] = ['status' => 'Present', 'payment' => $index === 0 ? '10' : '0'];
        }

        app(AttendanceService::class)->mark($activity, $this->admin(), $marks);

        return $activity;
    }

    public function test_totals_cover_the_whole_filtered_set_not_the_page(): void
    {
        $this->seedFees(25);

        $this->actingAs($this->admin())->get('/reports/class-fees')
            ->assertOk()
            ->assertSee('MVR 250.00')
            ->assertSee('MVR 240.00')
            ->assertSee('Next');
    }

    public function test_filters_apply_to_rows_and_totals(): void
    {
        $this->seedFees(3);

        $this->actingAs($this->admin())->get('/reports/class-fees?status=Paid')
            ->assertOk()->assertSee('MVR 10.00')->assertDontSee('MVR 30.00');
    }

    public function test_leader_reports_are_scoped(): void
    {
        $leader = $this->leader();
        $mine = Student::factory()->create(['name' => 'Scoped Scout']);
        $other = Student::factory()->create(['name' => 'Other Scout']);
        Group::factory()->ledBy($leader)->withMembers($mine)->create();
        $activity = Activity::factory()->forAll()->create();
        app(AttendanceService::class)->mark($activity, $this->admin(), [$mine->id => ['status' => 'Present'], $other->id => ['status' => 'Absent']]);

        $this->actingAs($leader)->get('/reports/attendance')->assertSee('Scoped Scout')->assertDontSee('Other Scout');
        $this->actingAs($this->parentOf($mine))->get('/reports/attendance')->assertForbidden();
    }

    public function test_xlsx_export_has_header_block_and_totals_row(): void
    {
        $this->seedFees(3);
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/reports/class-fees/export?format=xlsx');
        $response->assertOk();

        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame(config('scout.organisation'), $sheet[0][0]);
        $this->assertSame('Class fees report', $sheet[1][0]);
        $this->assertStringContainsString($admin->name, $sheet[2][0]);
        $this->assertSame(['Activity', 'Student', 'Fee', 'Paid', 'Outstanding', 'Status'], $sheet[4]);
        $last = end($sheet);
        $this->assertSame('Total (3 rows)', $last[0]);
        $this->assertEquals(30.0, (float) $last[2]);
    }

    public function test_csv_export_and_print_view(): void
    {
        $this->seedFees(2);
        $admin = $this->admin();

        $csv = $this->actingAs($admin)->get('/reports/payments/export?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString('Payments report', file_get_contents($csv->getFile()->getPathname()));

        $this->actingAs($admin)->get('/reports/attendance?print=1')->assertOk()->assertSee('Attendance report');
    }

    public function test_every_report_renders(): void
    {
        $this->seedFees(2);
        $admin = $this->admin();

        foreach (['attendance', 'rover-attendance', 'annual-fees', 'class-fees', 'payments', 'shop'] as $type) {
            $this->actingAs($admin)->get("/reports/{$type}")->assertOk();
        }

        $this->actingAs($admin)->get('/reports/unknown')->assertNotFound();
    }
}
