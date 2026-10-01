<?php

namespace Tests\Feature\Attendance;

use App\Enums\FeeStatus;
use App\Enums\PaymentStatus;
use App\Enums\ScoutSection;
use App\Exceptions\ScoutException;
use App\Livewire\Attendance\Mark;
use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\Payment;
use App\Models\Student;
use App\Services\ActivityRosterService;
use App\Services\AttendanceService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function mark(Activity $activity, array $marks, $actor = null): array
    {
        return app(AttendanceService::class)->mark($activity, $actor ?? $this->admin(), $marks);
    }

    public function test_roster_is_the_deduplicated_union_of_active_scouts(): void
    {
        $inGroupAndSection = Student::factory()->section(ScoutSection::Scout)->create();
        $sectionOnly = Student::factory()->section(ScoutSection::Scout)->create();
        $groupOnly = Student::factory()->section(ScoutSection::CubScout)->create();
        Student::factory()->section(ScoutSection::Rover)->create();
        Student::factory()->section(ScoutSection::Scout)->inactive()->create();
        $group = Group::factory()->withMembers($inGroupAndSection, $groupOnly)->create();

        $activity = Activity::factory()->forSections([ScoutSection::Scout])->forGroups($group)->create();

        $ids = app(ActivityRosterService::class)->resolveStudentIds($activity);
        sort($ids);
        $expected = [$inGroupAndSection->id, $sectionOnly->id, $groupOnly->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    public function test_all_students_roster_includes_every_active_scout(): void
    {
        Student::factory()->count(3)->create();
        Student::factory()->pending()->create();

        $this->assertCount(3, app(ActivityRosterService::class)->resolveStudentIds(Activity::factory()->forAll()->create()));
    }

    public function test_present_late_and_absent_create_class_fees_at_activity_fee(): void
    {
        [$a, $b, $c] = Student::factory()->count(3)->create();
        $activity = Activity::factory()->forAll()->charged('30.00')->create(['date' => '2026-05-01']);

        $this->mark($activity, [$a->id => ['status' => 'Present'], $b->id => ['status' => 'Late'], $c->id => ['status' => 'Absent']]);

        $this->assertSame(3, ClassFee::query()->count());
        $fee = ClassFee::query()->where('student_id', $a->id)->firstOrFail();
        $this->assertSame('30.00', $fee->amount);
        $this->assertSame('2026-05-15', $fee->due_date->toDateString());
        $this->assertSame(FeeStatus::Pending, $fee->status);
    }

    public function test_fee_defaults_to_settings_when_activity_has_no_amount(): void
    {
        app(SettingsService::class)->set('default_class_fee', '25.00');
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->create(['charge_fee' => true, 'fee_amount' => null]);

        $this->mark($activity, [$student->id => ['status' => 'Present']]);

        $this->assertSame('25.00', ClassFee::query()->firstOrFail()->amount);
    }

    public function test_excused_creates_no_fee_and_uncharged_activities_create_none(): void
    {
        $student = Student::factory()->create();
        $charged = Activity::factory()->forAll()->charged()->create();
        $free = Activity::factory()->forAll()->create();

        $this->mark($charged, [$student->id => ['status' => 'Excused']]);
        $this->mark($free, [$student->id => ['status' => 'Present']]);

        $this->assertSame(0, ClassFee::query()->count());
    }

    public function test_changing_to_excused_voids_unpaid_fee_and_back_reopens_it(): void
    {
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->charged('20.00')->create();

        $this->mark($activity, [$student->id => ['status' => 'Present']]);
        $this->mark($activity, [$student->id => ['status' => 'Excused']]);

        $fee = ClassFee::query()->firstOrFail();
        $this->assertSame(FeeStatus::Void, $fee->status);
        $this->assertSame('0.00', $fee->outstanding_amount);
        $this->assertNotNull($fee->voided_at);

        $this->mark($activity, [$student->id => ['status' => 'Late']]);

        $fee->refresh();
        $this->assertSame(FeeStatus::Pending, $fee->status);
        $this->assertNull($fee->voided_at);
        $this->assertSame('20.00', $fee->outstanding_amount);
        $this->assertSame(1, ClassFee::query()->count());
        $this->assertSame(1, AttendanceRecord::query()->count());
    }

    public function test_excused_leaves_a_fee_with_payment_in_place(): void
    {
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->charged('20.00')->create();

        $this->mark($activity, [$student->id => ['status' => 'Present', 'payment' => '10']]);
        $this->mark($activity, [$student->id => ['status' => 'Excused']]);

        $fee = ClassFee::query()->firstOrFail();
        $this->assertSame(FeeStatus::Partial, $fee->status);
        $this->assertSame('10.00', $fee->paid_amount);
    }

    public function test_roster_payments_preset_custom_zero_and_cap(): void
    {
        [$a, $b, $c, $d] = Student::factory()->count(4)->create();
        $activity = Activity::factory()->forAll()->charged('12.00')->create();

        $this->mark($activity, [
            $a->id => ['status' => 'Present', 'payment' => '5'],
            $b->id => ['status' => 'Present', 'payment' => '7.50'],
            $c->id => ['status' => 'Present', 'payment' => '0'],
            $d->id => ['status' => 'Present', 'payment' => '15'],
        ]);

        $paid = fn (Student $s) => ClassFee::query()->where('student_id', $s->id)->firstOrFail();
        $this->assertSame('5.00', $paid($a)->paid_amount);
        $this->assertSame('7.50', $paid($b)->paid_amount);
        $this->assertSame('0.00', $paid($c)->paid_amount);
        $this->assertSame('12.00', $paid($d)->paid_amount);
        $this->assertSame(FeeStatus::Paid, $paid($d)->status);

        $roster = Payment::query()->where('student_id', $a->id)->firstOrFail();
        $this->assertSame(Payment::SOURCE_ROSTER, $roster->source);
        $this->assertSame(PaymentStatus::Paid, $roster->status);
    }

    public function test_changing_roster_payment_to_zero_rejects_it(): void
    {
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->charged('10.00')->create();

        $this->mark($activity, [$student->id => ['status' => 'Present', 'payment' => '10']]);
        $this->mark($activity, [$student->id => ['status' => 'Present', 'payment' => '0']]);

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::Rejected, $payment->status);
        $this->assertSame('Marked not paid on the attendance roster.', $payment->rejection_reason);
        $this->assertSame('0.00', ClassFee::query()->firstOrFail()->paid_amount);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_roster_payment_subtracts_other_approved_payments(): void
    {
        $student = Student::factory()->create();
        $activity = Activity::factory()->forAll()->charged('10.00')->create();
        $this->mark($activity, [$student->id => ['status' => 'Present']]);
        $fee = ClassFee::query()->firstOrFail();
        Payment::query()->create([
            'payable_type' => 'class_fee', 'payable_id' => $fee->id, 'student_id' => $student->id,
            'amount' => '6.00', 'method' => 'cash', 'status' => 'Paid', 'submitted_at' => now(),
        ]);

        $this->mark($activity, [$student->id => ['status' => 'Present', 'payment' => '10']]);

        $this->assertSame('4.00', Payment::query()->where('source', 'roster')->firstOrFail()->amount);
        $this->assertSame(FeeStatus::Paid, $fee->fresh()->status);
    }

    public function test_leader_records_roster_cash_without_verify_permission(): void
    {
        $leader = $this->leader();
        $student = Student::factory()->create();
        $group = Group::factory()->ledBy($leader)->withMembers($student)->create();
        $activity = Activity::factory()->forGroups($group)->charged('10.00')->create();

        $this->mark($activity, [$student->id => ['status' => 'Present', 'payment' => '10']], $leader);

        $this->assertSame(FeeStatus::Paid, ClassFee::query()->firstOrFail()->status);
    }

    public function test_marking_a_scout_outside_scope_fails_the_whole_save(): void
    {
        $leader = $this->leader();
        $mine = Student::factory()->create();
        $other = Student::factory()->create();
        Group::factory()->ledBy($leader)->withMembers($mine)->create();
        $activity = Activity::factory()->forAll()->create();

        try {
            $this->mark($activity, [$mine->id => ['status' => 'Present'], $other->id => ['status' => 'Present']], $leader);
            $this->fail('Expected the save to fail.');
        } catch (ScoutException) {
        }

        $this->assertSame(0, AttendanceRecord::query()->count());
    }

    public function test_leader_without_groups_cannot_manage_attendance(): void
    {
        $leader = $this->leader();
        $activity = Activity::factory()->forAll()->create();

        $this->actingAs($leader)->get("/attendance/{$activity->uuid}/mark")->assertForbidden();
    }

    public function test_roster_fee_update_reprices_non_void_fees(): void
    {
        [$a, $b] = Student::factory()->count(2)->create();
        $activity = Activity::factory()->forAll()->charged('10.00')->create();
        $this->mark($activity, [$a->id => ['status' => 'Present', 'payment' => '10'], $b->id => ['status' => 'Present']]);

        Livewire::actingAs($this->admin())->test(Mark::class, ['activity' => $activity])
            ->set('feeDue', '15')
            ->call('updateFee')
            ->assertSet('error', null);

        $feeA = ClassFee::query()->where('student_id', $a->id)->firstOrFail();
        $this->assertSame('15.00', $feeA->amount);
        $this->assertSame('5.00', $feeA->outstanding_amount);
        $this->assertSame(FeeStatus::Partial, $feeA->status);
        $this->assertSame('15.00', $activity->fresh()->fee_amount);
    }

    public function test_uncharged_activity_cannot_change_fee(): void
    {
        $activity = Activity::factory()->forAll()->create();
        Student::factory()->create();

        Livewire::actingAs($this->admin())->test(Mark::class, ['activity' => $activity])
            ->set('feeDue', '15')
            ->call('updateFee')
            ->assertSet('error', 'This activity does not charge a fee.');
    }

    public function test_register_component_saves_partial_register_and_payment_choice(): void
    {
        [$a, $b] = Student::factory()->count(2)->create();
        $activity = Activity::factory()->forAll()->charged('10.00')->create();

        Livewire::actingAs($this->admin())->test(Mark::class, ['activity' => $activity])
            ->set("rows.{$a->id}.status", 'Present')
            ->set("rows.{$a->id}.payment", 'other')
            ->set("rows.{$a->id}.other", '8')
            ->call('save')
            ->assertSet('flash', 'Saved 1 mark(s).');

        $this->assertSame(1, AttendanceRecord::query()->count());
        $this->assertSame('8.00', ClassFee::query()->where('student_id', $a->id)->firstOrFail()->paid_amount);
        $this->assertFalse(ClassFee::query()->where('student_id', $b->id)->exists());
    }

    public function test_mark_all_present(): void
    {
        [$a, $b] = Student::factory()->count(2)->create();
        $activity = Activity::factory()->forAll()->create();

        Livewire::actingAs($this->admin())->test(Mark::class, ['activity' => $activity])
            ->call('markAllPresent')
            ->call('save');

        $this->assertSame(2, AttendanceRecord::query()->where('status', 'Present')->count());
    }
}
