<?php

namespace App\Services;

use App\Contracts\Payable;
use App\Enums\FeeStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Models\AnnualFee;
use App\Models\ClassFee;
use App\Models\Payment;
use App\Models\Purchase;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Balances are never typed in: paid, outstanding and status always derive
 * from approved (Paid) payments.
 */
class PaymentBalanceService
{
    public function approvedTotal(Model&Payable $payable): string
    {
        $total = Payment::query()
            ->where('payable_type', $payable->payableTypeKey())
            ->where('payable_id', $payable->getKey())
            ->where('status', PaymentStatus::Paid->value)
            ->pluck('amount')
            ->all();

        return Money::add('0', ...$total);
    }

    public function hasAwaiting(Model&Payable $payable): bool
    {
        return Payment::query()
            ->where('payable_type', $payable->payableTypeKey())
            ->where('payable_id', $payable->getKey())
            ->where('status', PaymentStatus::AwaitingVerification->value)
            ->exists();
    }

    public function recalculate(Model&Payable $payable): Model&Payable
    {
        return match (true) {
            $payable instanceof ClassFee => $this->calculateClassFeeBalance($payable),
            $payable instanceof AnnualFee => $this->calculateAnnualFeeBalance($payable),
            $payable instanceof Purchase => $this->calculatePurchaseBalance($payable),
        };
    }

    public function calculateClassFeeBalance(ClassFee $fee): ClassFee
    {
        $paid = $this->approvedTotal($fee);

        if ($fee->status === FeeStatus::Void) {
            $fee->forceFill(['paid_amount' => $paid, 'outstanding_amount' => '0.00'])->save();

            return $fee;
        }

        [$outstanding, $status] = $this->derive($fee->amount, $paid, $this->hasAwaiting($fee));
        $fee->forceFill(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'status' => $status])->save();

        return $fee;
    }

    public function calculateAnnualFeeBalance(AnnualFee $fee): AnnualFee
    {
        $paid = $this->approvedTotal($fee);
        [$outstanding, $status] = $this->derive($fee->amount, $paid, $this->hasAwaiting($fee));
        $fee->forceFill(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'status' => $status])->save();

        return $fee;
    }

    public function calculatePurchaseBalance(Purchase $purchase): Purchase
    {
        $paid = $this->approvedTotal($purchase);
        [$outstanding, $status] = $this->derive($purchase->total_amount, $paid, $this->hasAwaiting($purchase));
        $purchase->forceFill(['paid_amount' => $paid, 'outstanding_amount' => $outstanding, 'payment_status' => $status]);
        $this->syncPurchaseStatus($purchase);
        $purchase->save();

        return $purchase;
    }

    /**
     * Drive purchase_status from payment_status. Cancelled is never touched.
     */
    public function syncPurchaseStatus(Purchase $purchase): void
    {
        if ($purchase->purchase_status === PurchaseStatus::Cancelled) {
            return;
        }

        $purchase->purchase_status = match ($purchase->payment_status) {
            FeeStatus::Paid => in_array($purchase->purchase_status, [PurchaseStatus::ReadyForCollection, PurchaseStatus::Delivered], true)
                ? $purchase->purchase_status
                : PurchaseStatus::Confirmed,
            FeeStatus::AwaitingVerification => PurchaseStatus::PaymentVerification,
            default => PurchaseStatus::PendingPayment,
        };
    }

    /**
     * @return array{0: string, 1: FeeStatus}
     */
    private function derive(string|float|null $amount, string $paid, bool $awaiting): array
    {
        $outstanding = Money::max('0', Money::sub($amount, $paid));

        $status = match (true) {
            Money::compare($paid, $amount) >= 0 && Money::isPositive($amount) => FeeStatus::Paid,
            Money::isPositive($paid) => FeeStatus::Partial,
            $awaiting => FeeStatus::AwaitingVerification,
            default => FeeStatus::Pending,
        };

        return [$outstanding, $status];
    }
}
