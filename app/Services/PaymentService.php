<?php

namespace App\Services;

use App\Contracts\Payable;
use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PersonType;
use App\Enums\PurchaseStatus;
use App\Exceptions\InvalidPaymentProof;
use App\Exceptions\PaymentNotVerifiable;
use App\Exceptions\ScoutException;
use App\Exceptions\StudentNotAccessible;
use App\Models\AnnualFee;
use App\Models\ClassFee;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\Purchase;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Payment lifecycle: submit (online with proof, or staff cash), approve, reject.
 */
class PaymentService
{
    public const PROOF_DISK = 'local';

    public const TYPES = ['class_fee' => ClassFee::class, 'annual_fee' => AnnualFee::class, 'purchase' => Purchase::class];

    public function __construct(
        private PaymentBalanceService $balances,
        private InventoryService $inventory,
        private LeaderScopeService $scope,
        private SettingsService $settings,
        private AuditLogService $audit,
        private NotificationService $notifications,
    ) {}

    public function resolvePayable(string $type, string $uuid): Model&Payable
    {
        $class = self::TYPES[$type] ?? null;
        abort_if($class === null, 404);

        return $class::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function canRecordCash(User $user): bool
    {
        return $user->isActive() && $user->hasPermission(Permission::VerifyPayments);
    }

    public function canPay(User $user, Model&Payable $payable): bool
    {
        if ($payable instanceof AnnualFee && $payable->person_type === PersonType::Leader) {
            return $user->isAdmin() || $payable->user_id === $user->id;
        }

        $studentId = $payable->payableStudentId();

        return $studentId !== null && $this->scope->canAccessStudent($user, Student::withTrashed()->findOrFail($studentId));
    }

    public function submit(Model&Payable $payable, User $actor, string $amount, PaymentMethod $method, ?UploadedFile $proof = null): Payment
    {
        if (! $this->canPay($actor, $payable)) {
            throw new StudentNotAccessible('You cannot pay for this record.');
        }

        if ($method === PaymentMethod::Cash && ! $this->canRecordCash($actor)) {
            throw new ScoutException('Only authorised staff can record a cash payment.');
        }

        if ($method->requiresProof() && $proof === null) {
            throw new InvalidPaymentProof('Online payment requires a proof file.');
        }

        if (Money::compare($amount, '0.01') < 0) {
            throw new ScoutException('Enter an amount of at least 0.01.');
        }

        $this->assertPayable($payable);

        $uuid = (string) Str::uuid();
        $storedPath = $proof ? $this->storeProofFile($proof, $uuid) : null;

        try {
            return DB::transaction(function () use ($payable, $actor, $amount, $method, $proof, $storedPath, $uuid): Payment {
                $staffCash = $method === PaymentMethod::Cash;

                $payment = Payment::query()->forceCreate([
                    'uuid' => $uuid,
                    'payable_type' => $payable->payableTypeKey(),
                    'payable_id' => $payable->getKey(),
                    'student_id' => $payable->payableStudentId(),
                    'amount' => Money::normalize($amount),
                    'method' => $method,
                    'submitted_by' => $actor->id,
                    'submitted_at' => now(),
                    'status' => $staffCash ? PaymentStatus::Paid : PaymentStatus::AwaitingVerification,
                    'accepted_by' => $staffCash ? $actor->id : null,
                    'verified_by' => $staffCash ? $actor->id : null,
                    'verified_at' => $staffCash ? now() : null,
                ]);

                if ($proof && $storedPath) {
                    PaymentProof::query()->create([
                        'payment_id' => $payment->id,
                        'disk' => self::PROOF_DISK,
                        'path' => $storedPath,
                        'original_filename' => mb_substr($proof->getClientOriginalName(), 0, 255),
                        'mime_type' => $proof->getMimeType(),
                        'file_size' => $proof->getSize(),
                        'uploaded_by' => $actor->id,
                        'uploaded_at' => now(),
                    ]);
                }

                $this->audit->record('payment.submitted', $payment, [
                    'type' => $payment->payable_type,
                    'amount' => $payment->amount,
                    'method' => $method,
                    'status' => $payment->status,
                ], $actor);

                $this->balances->recalculate($payable);
                $this->inventory->decrementForPayable($payable, $actor);

                if ($payment->status === PaymentStatus::AwaitingVerification) {
                    DB::afterCommit(fn () => $this->notifications->paymentSubmitted($payment));
                }

                return $payment;
            });
        } catch (Throwable $e) {
            if ($storedPath) {
                Storage::disk(self::PROOF_DISK)->delete($storedPath);
            }

            throw $e;
        }
    }

    public function approve(Payment $payment, User $actor): Payment
    {
        $this->assertVerifier($actor);

        $payment = DB::transaction(function () use ($payment, $actor): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::AwaitingVerification) {
                throw new PaymentNotVerifiable('Only payments awaiting verification can be approved.');
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'accepted_by' => $actor->id,
            ])->save();

            $payable = $locked->payable;
            $this->balances->recalculate($payable);
            $this->inventory->decrementForPayable($payable, $actor);
            $this->audit->record('payment.approved', $locked, ['amount' => $locked->amount], $actor);

            return $locked;
        });

        $this->notifications->paymentDecided($payment);

        return $payment;
    }

    public function reject(Payment $payment, User $actor, string $reason): Payment
    {
        $this->assertVerifier($actor);

        if (trim($reason) === '') {
            throw new ScoutException('A reason is required to reject a payment.');
        }

        $payment = DB::transaction(function () use ($payment, $actor, $reason): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::AwaitingVerification) {
                throw new PaymentNotVerifiable('Only payments awaiting verification can be rejected.');
            }

            $locked->forceFill([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            $this->balances->recalculate($locked->payable);
            $this->audit->record('payment.rejected', $locked, ['reason' => $reason], $actor);

            return $locked;
        });

        $this->notifications->paymentDecided($payment);

        return $payment;
    }

    /**
     * One auto-approved cash payment per class fee from the attendance roster.
     * A zero amount rejects the roster payment.
     */
    public function upsertRosterCash(ClassFee $fee, string $amount, User $actor): ?Payment
    {
        $existing = Payment::query()
            ->where('payable_type', 'class_fee')
            ->where('payable_id', $fee->id)
            ->where('source', Payment::SOURCE_ROSTER)
            ->lockForUpdate()
            ->first();

        if (! Money::isPositive($amount)) {
            if ($existing && $existing->status !== PaymentStatus::Rejected) {
                $existing->forceFill([
                    'status' => PaymentStatus::Rejected,
                    'rejection_reason' => 'Marked not paid on the attendance roster.',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                ])->save();
                $this->audit->record('payment.roster_rejected', $existing, [], $actor);
            }

            return $existing;
        }

        $attributes = [
            'amount' => Money::normalize($amount),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Paid,
            'rejection_reason' => null,
            'submitted_by' => $actor->id,
            'submitted_at' => now(),
            'accepted_by' => $actor->id,
            'verified_by' => $actor->id,
            'verified_at' => now(),
        ];

        if ($existing) {
            $existing->forceFill($attributes)->save();
            $this->audit->record('payment.roster_updated', $existing, ['amount' => $existing->amount], $actor);

            return $existing;
        }

        $payment = Payment::query()->create($attributes + [
            'payable_type' => 'class_fee',
            'payable_id' => $fee->id,
            'student_id' => $fee->student_id,
            'source' => Payment::SOURCE_ROSTER,
        ]);
        $this->audit->record('payment.roster_recorded', $payment, ['amount' => $payment->amount], $actor);

        return $payment;
    }

    /**
     * Validate and store a proof on the private disk; the file must exist before any payment row.
     */
    public function storeProofFile(UploadedFile $file, string $paymentUuid): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());
        $mimes = $this->settings->proofMimes();

        if (! $file->isValid() || ! in_array($extension, $mimes, true) || ! in_array(strtolower((string) $file->extension()), array_merge($mimes, ['jpg']), true)) {
            throw new InvalidPaymentProof('The proof must be one of: '.strtoupper(implode(', ', $mimes)).'.');
        }

        if ($file->getSize() > $this->settings->proofMaxBytes()) {
            throw new InvalidPaymentProof('The proof file is too large (maximum '.round($this->settings->proofMaxKb() / 1024, 1).' MB).');
        }

        $path = Uploads::store($file, 'payment-proofs', $paymentUuid.'.'.$extension, self::PROOF_DISK);

        if (! $path || ! Storage::disk(self::PROOF_DISK)->exists($path)) {
            throw new InvalidPaymentProof('The proof could not be saved. Please try again.');
        }

        return $path;
    }

    private function assertPayable(Model&Payable $payable): void
    {
        if ($payable instanceof ClassFee && $payable->status === FeeStatus::Void) {
            throw new ScoutException('This class fee was voided and cannot be paid.');
        }

        if ($payable instanceof Purchase && $payable->purchase_status === PurchaseStatus::Cancelled) {
            throw new ScoutException('This purchase was cancelled.');
        }

        $status = $payable instanceof Purchase ? $payable->payment_status : $payable->status;

        if ($status === FeeStatus::Paid) {
            throw new ScoutException('This is already fully paid.');
        }
    }

    private function assertVerifier(User $actor): void
    {
        if (! $this->canRecordCash($actor)) {
            throw new PaymentNotVerifiable('Only admins or users with the Verify payments permission can approve or reject payments.');
        }
    }
}
