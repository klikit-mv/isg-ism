<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PurchaseStatus: string
{
    use HasOptions;

    case PendingPayment = 'PendingPayment';
    case PaymentVerification = 'PaymentVerification';
    case Confirmed = 'Confirmed';
    case ReadyForCollection = 'ReadyForCollection';
    case Delivered = 'Delivered';
    case Cancelled = 'Cancelled';

    public function isPaidLifecycle(): bool
    {
        return in_array($this, [self::Confirmed, self::ReadyForCollection, self::Delivered], true);
    }

    public function labelText(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::PaymentVerification => 'Payment verification',
            self::ReadyForCollection => 'Ready for collection',
            default => $this->value,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingPayment => 'red',
            self::PaymentVerification => 'amber',
            self::Confirmed => 'blue',
            self::ReadyForCollection => 'purple',
            self::Delivered => 'green',
            self::Cancelled => 'gray',
        };
    }
}
