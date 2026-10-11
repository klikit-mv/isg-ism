<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\ScoutException;
use App\Models\Activity;
use App\Models\ClassFee;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Class fees follow attendance: one per activity per scout, created, voided
 * or re-opened as marks change.
 */
class ClassFeeService
{
    public function __construct(
        private SettingsService $settings,
        private PaymentBalanceService $balances,
        private PaymentService $payments,
        private AuditLogService $audit,
    ) {}

    public function amountFor(Activity $activity): string
    {
        return $activity->fee_amount !== null && Money::isPositive($activity->fee_amount)
            ? Money::normalize($activity->fee_amount)
            : $this->settings->defaultClassFee();
    }

    /**
     * Apply the class-fee rules for one attendance mark. Call inside the attendance transaction.
     */
    public function syncForAttendance(Activity $activity, Student $student, AttendanceStatus $status, User $actor): ?ClassFee
    {
        if (! $activity->charge_fee) {
            return null;
        }

        $fee = ClassFee::query()->where('activity_id', $activity->id)->where('student_id', $student->id)->lockForUpdate()->first();

        if ($status->generatesClassFee()) {
            if ($fee === null) {
                $fee = ClassFee::query()->create([
                    'activity_id' => $activity->id,
                    'student_id' => $student->id,
                    'amount' => $this->amountFor($activity),
                    'paid_amount' => '0.00',
                    'outstanding_amount' => $this->amountFor($activity),
                    'status' => FeeStatus::Pending,
                    'due_date' => $activity->date->copy()->addDays(14),
                    'created_by' => $actor->id,
                ]);
                $this->audit->record('class_fee.created', $fee, ['amount' => $fee->amount], $actor);

                return $fee;
            }

            if ($fee->status === FeeStatus::Void) {
                $fee->forceFill([
                    'status' => FeeStatus::Pending,
                    'amount' => $this->amountFor($activity),
                    'voided_at' => null,
                ])->save();
                $this->balances->calculateClassFeeBalance($fee);
                $this->audit->record('class_fee.reopened', $fee, [], $actor);
            }

            return $fee;
        }

        // Excused: void an unpaid fee; a fee with any payment is left in place.
        if ($fee !== null && $fee->status !== FeeStatus::Void && ! Money::isPositive($fee->paid_amount) && ! $this->hasLivePayments($fee)) {
            $fee->forceFill([
                'status' => FeeStatus::Void,
                'outstanding_amount' => '0.00',
                'voided_at' => now(),
            ])->save();
            $this->audit->record('class_fee.voided', $fee, [], $actor);
        }

        return $fee;
    }

    /**
     * Change the fee due on a charged activity and re-price every non-void fee.
     */
    public function updateActivityFee(Activity $activity, string $amount, User $actor): int
    {
        if (! $activity->charge_fee) {
            throw new ScoutException('This activity does not charge a fee.');
        }

        if (Money::compare($amount, '0') < 0) {
            throw new ScoutException('The fee cannot be negative.');
        }

        return DB::transaction(function () use ($activity, $amount, $actor): int {
            $from = $activity->fee_amount;
            $activity->update(['fee_amount' => Money::normalize($amount)]);

            $fees = ClassFee::query()->where('activity_id', $activity->id)->where('status', '!=', FeeStatus::Void->value)->lockForUpdate()->get();

            foreach ($fees as $fee) {
                $fee->forceFill(['amount' => Money::normalize($amount)])->save();
                $this->balances->calculateClassFeeBalance($fee);
            }

            $this->audit->record('activity.fee_updated', $activity, ['from' => $from, 'to' => Money::normalize($amount), 'fees' => $fees->count()], $actor);

            return $fees->count();
        });
    }

    /**
     * @return array<string, string>
     */
    public function rosterPaymentChoices(): array
    {
        return ['0' => 'Not paid', '5' => '5', '10' => '10', '15' => '15', 'other' => 'Other'];
    }

    /**
     * Record the cash taken while marking. The amount is capped at the fee due
     * less other approved (non-roster) payments. Zero rejects the roster payment.
     */
    public function settleRosterPayment(ClassFee $fee, string $choice, User $actor): ?Payment
    {
        if ($fee->status === FeeStatus::Void) {
            return null;
        }

        $otherPaid = Money::add('0', ...Payment::query()
            ->where('payable_type', 'class_fee')
            ->where('payable_id', $fee->id)
            ->where('status', PaymentStatus::Paid->value)
            ->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', Payment::SOURCE_ROSTER))
            ->pluck('amount')
            ->all());

        $room = Money::max('0', Money::sub($fee->amount, $otherPaid));
        $amount = Money::min(Money::max('0', $choice), $room);

        $payment = $this->payments->upsertRosterCash($fee, $amount, $actor);
        $this->balances->calculateClassFeeBalance($fee);

        return $payment;
    }

    private function hasLivePayments(ClassFee $fee): bool
    {
        return Payment::query()
            ->where('payable_type', 'class_fee')
            ->where('payable_id', $fee->id)
            ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::AwaitingVerification->value])
            ->exists();
    }
}
