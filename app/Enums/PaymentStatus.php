<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case Pending = 'Pending';
    case AwaitingVerification = 'AwaitingVerification';
    case Paid = 'Paid';
    case Rejected = 'Rejected';

    public function countsTowardBalance(): bool
    {
        return $this === self::Paid;
    }

    public function labelText(): string
    {
        return match ($this) {
            self::AwaitingVerification => 'Awaiting verification',
            self::Paid => 'Approved',
            default => $this->value,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::AwaitingVerification => 'amber',
            self::Pending => 'gray',
            self::Rejected => 'red',
        };
    }
}
