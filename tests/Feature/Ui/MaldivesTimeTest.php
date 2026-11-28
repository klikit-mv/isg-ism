<?php

namespace Tests\Feature\Ui;

use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Models\BankAccount;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaldivesTimeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_today_and_dates_follow_maldives_time_not_utc(): void
    {
        // 20:30 UTC on 31 December is already 01:30 on 1 January in the Maldives (UTC+5).
        Carbon::setTestNow(Carbon::parse('2026-12-31 20:30:00', 'UTC'));

        $this->assertSame('2027-01-01', scout_today());
        $this->assertSame('2027', scout_now()->format('Y'));
        $this->assertSame('01.01.2027', scout_date(now()));
        $this->assertSame('1 January 2027', scout_long_date(now()));
        $this->assertSame('01.01.2027 01:30', scout_datetime(now()));
        $this->assertSame('05.05.2026', scout_date('2026-05-05'));
    }

    public function test_forms_default_to_the_maldives_date_and_accept_it(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 20:30:00', 'UTC'));
        $admin = $this->admin();

        $this->actingAs($admin)->get('/certificates/create')->assertOk()->assertSee('value="2027-01-01"', false);
        $account = BankAccount::query()->create(['name' => 'A', 'bank_name' => 'B', 'account_number' => '1', 'opening_balance' => '0', 'status' => 'Active']);
        $this->actingAs($admin)->post(route('bank.record', [$account, 'expense']), ['amount' => '1', 'date' => '2027-01-01', 'party' => 'X', 'purpose' => 'Y'])
            ->assertSessionDoesntHaveErrors('date');
        $this->actingAs($admin)->post(route('bank.record', [$account, 'expense']), ['amount' => '1', 'date' => '2027-01-02', 'party' => 'X', 'purpose' => 'Y'])
            ->assertSessionHasErrors('date');
    }

    public function test_year_filters_open_on_the_current_maldives_year_and_any_year_still_works(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-31 20:30:00', 'UTC'));
        $admin = $this->admin();
        $old = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        $new = AnnualFeeYear::query()->create(['year' => 2027, 'amount' => '120.00', 'status' => 'Active']);
        $first = Student::factory()->create(['name' => 'Billed Last Year']);
        $second = Student::factory()->create(['name' => 'Billed This Year']);
        foreach ([[$old, $first], [$new, $second]] as [$year, $student]) {
            AnnualFee::query()->create(['annual_fee_year_id' => $year->id, 'student_id' => $student->id, 'person_type' => 'Student', 'amount' => $year->amount, 'paid_amount' => '0.00', 'outstanding_amount' => $year->amount, 'status' => 'Pending']);
        }

        $this->actingAs($admin)->get('/annual-fees')->assertOk()->assertSee('Billed This Year')->assertDontSee('Billed Last Year');
        $this->actingAs($admin)->get('/annual-fees?year=2026')->assertSee('Billed Last Year')->assertDontSee('Billed This Year');
        $this->actingAs($admin)->get('/annual-fees?year=')->assertSee('Billed Last Year')->assertSee('Billed This Year');
        $this->actingAs($admin)->get('/reports/annual-fees')->assertOk()->assertSee('Billed This Year')->assertDontSee('Billed Last Year');
        $this->actingAs($admin)->get('/reports/annual-fees?year=')->assertSee('Billed Last Year')->assertSee('Billed This Year');
    }

    public function test_when_the_current_year_has_no_fee_year_the_list_is_not_filtered(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-06-01 10:00:00', 'UTC'));
        $year = AnnualFeeYear::query()->create(['year' => 2026, 'amount' => '100.00', 'status' => 'Active']);
        $student = Student::factory()->create(['name' => 'Only Invoice']);
        AnnualFee::query()->create(['annual_fee_year_id' => $year->id, 'student_id' => $student->id, 'person_type' => 'Student', 'amount' => '100.00', 'paid_amount' => '0.00', 'outstanding_amount' => '100.00', 'status' => 'Pending']);

        $this->actingAs($this->admin())->get('/annual-fees')->assertSee('Only Invoice');
    }
}
