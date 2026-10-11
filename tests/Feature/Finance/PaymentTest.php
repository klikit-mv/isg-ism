<?php

namespace Tests\Feature\Finance;

use App\Enums\FeeStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\ClassFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function feeFor(Student $student, string $amount = '20.00'): ClassFee
    {
        $activity = Activity::factory()->forAll()->charged($amount)->create();
        app(AttendanceService::class)->mark($activity, User::factory()->admin()->create(), [$student->id => ['status' => 'Present']]);

        return ClassFee::query()->where('student_id', $student->id)->firstOrFail();
    }

    private function pay(User $user, ClassFee $fee, array $overrides = [])
    {
        return $this->actingAs($user)->post('/payments', array_merge([
            'payable_type' => 'class_fee',
            'payable_id' => $fee->uuid,
            'amount' => '20.00',
            'method' => 'online',
            'proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ], $overrides));
    }

    public function test_parent_pays_online_with_proof_and_verifiers_are_notified(): void
    {
        Notification::fake();
        $verifier = $this->leader(Permission::VerifyPayments);
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $fee = $this->feeFor($student);

        $this->pay($parent, $fee)->assertSessionHas('success');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::AwaitingVerification, $payment->status);
        Storage::disk('local')->assertExists($payment->proof->path);
        $this->assertSame("payment-proofs/{$payment->uuid}.pdf", $payment->proof->path);
        $this->assertSame(FeeStatus::AwaitingVerification, $fee->fresh()->status);
        $this->assertSame('0.00', $fee->fresh()->paid_amount);
        Notification::assertSentTo($verifier, ScoutAlert::class);
    }

    public function test_online_payment_requires_a_proof(): void
    {
        $student = Student::factory()->create();
        $fee = $this->feeFor($student);

        $this->pay($this->parentOf($student), $fee, ['proof' => null])
            ->assertSessionHas('error', 'Online payment requires a proof file.');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_failed_upload_creates_no_payment_and_no_balance_change(): void
    {
        $student = Student::factory()->create();
        $fee = $this->feeFor($student);

        $this->pay($this->parentOf($student), $fee, ['proof' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame(FeeStatus::Pending, $fee->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles('payment-proofs'));
    }

    public function test_staff_cash_is_auto_approved(): void
    {
        $student = Student::factory()->create();
        $fee = $this->feeFor($student);

        $this->pay($this->admin(), $fee, ['method' => 'cash', 'proof' => null])->assertSessionHas('success');

        $this->assertSame(PaymentStatus::Paid, Payment::query()->firstOrFail()->status);
        $this->assertSame(FeeStatus::Paid, $fee->fresh()->status);
    }

    public function test_parents_and_students_cannot_record_cash(): void
    {
        $student = Student::factory()->create();
        $fee = $this->feeFor($student);

        $this->pay($this->parentOf($student), $fee, ['method' => 'cash', 'proof' => null])
            ->assertSessionHas('error', 'Only authorised staff can record a cash payment.');
        $this->pay($this->studentUser($student), $fee, ['method' => 'cash', 'proof' => null])
            ->assertSessionHas('error', 'Only authorised staff can record a cash payment.');

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_parent_cannot_pay_for_someone_elses_child(): void
    {
        $fee = $this->feeFor(Student::factory()->create());

        $this->pay($this->parentOf(Student::factory()->create()), $fee)->assertSessionHas('error');
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_approval_counts_the_payment_and_rejection_does_not(): void
    {
        $verifier = $this->leader(Permission::VerifyPayments);
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $fee = $this->feeFor($student);

        $this->pay($parent, $fee, ['amount' => '5']);
        $this->pay($parent, $fee, ['amount' => '7']);
        [$first, $second] = Payment::query()->orderBy('id')->get();

        $this->actingAs($verifier)->post("/payments/{$first->uuid}/approve")->assertSessionHas('success');
        $this->actingAs($verifier)->post("/payments/{$second->uuid}/reject", ['reason' => 'Blurry receipt'])->assertSessionHas('success');

        $fee->refresh();
        $this->assertSame('5.00', $fee->paid_amount);
        $this->assertSame('15.00', $fee->outstanding_amount);
        $this->assertSame(FeeStatus::Partial, $fee->status);
        $this->assertSame('Blurry receipt', $second->fresh()->rejection_reason);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $student = Student::factory()->create();
        $this->pay($this->parentOf($student), $this->feeFor($student));
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($this->admin())->post("/payments/{$payment->uuid}/reject", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(PaymentStatus::AwaitingVerification, $payment->fresh()->status);
    }

    public function test_only_verifiers_can_approve_or_reject(): void
    {
        $student = Student::factory()->create();
        $this->pay($this->parentOf($student), $this->feeFor($student));
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($this->leader())->post("/payments/{$payment->uuid}/approve")->assertForbidden();
        $this->actingAs($this->leader())->get('/payment-verification')->assertForbidden();
        $this->assertSame(PaymentStatus::AwaitingVerification, $payment->fresh()->status);
    }

    public function test_a_payment_can_only_be_decided_once(): void
    {
        $student = Student::factory()->create();
        $this->pay($this->parentOf($student), $this->feeFor($student));
        $payment = Payment::query()->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/payments/{$payment->uuid}/approve");
        $this->actingAs($admin)->post("/payments/{$payment->uuid}/reject", ['reason' => 'Oops'])
            ->assertSessionHas('error', 'Only payments awaiting verification can be rejected.');
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
    }

    public function test_decision_notifies_the_submitter(): void
    {
        Notification::fake();
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $this->pay($parent, $this->feeFor($student));

        $this->actingAs($this->admin())->post('/payments/'.Payment::query()->firstOrFail()->uuid.'/reject', ['reason' => 'Wrong amount']);

        Notification::assertSentTo($parent, ScoutAlert::class, fn ($n) => str_contains($n->body, 'Wrong amount'));
    }

    public function test_proof_download_is_limited_to_people_who_can_see_the_payment(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $this->pay($parent, $this->feeFor($student));
        $payment = Payment::query()->firstOrFail();

        $this->actingAs($parent)->get("/payments/{$payment->uuid}/proof")->assertOk();
        $this->actingAs($this->parentOf(Student::factory()->create()))->get("/payments/{$payment->uuid}/proof")->assertForbidden();
        $this->actingAs($this->leader(Permission::VerifyPayments))->get("/payments/{$payment->uuid}/proof")->assertOk();
    }

    public function test_payments_list_is_scoped(): void
    {
        $student = Student::factory()->create(['name' => 'Visible Child']);
        $parent = $this->parentOf($student);
        $this->pay($parent, $this->feeFor($student));
        $other = Student::factory()->create(['name' => 'Hidden Child']);
        $this->pay($this->parentOf($other), $this->feeFor($other));

        $this->actingAs($parent)->get('/payments')->assertSee('Visible Child')->assertDontSee('Hidden Child');
    }

    public function test_class_fee_list_shows_statistics_for_the_scope(): void
    {
        $student = Student::factory()->create();
        $this->feeFor($student, '20.00');
        $this->feeFor(Student::factory()->create(), '30.00');

        $this->actingAs($this->parentOf($student))->get('/class-fees')
            ->assertOk()->assertSee(scout_money('20.00'))->assertDontSee(scout_money('50.00'));
        $this->actingAs($this->admin())->get('/class-fees')->assertSee(scout_money('50.00'));
    }
}
