<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum FeeStatus: string
{
    use HasOptions;

    case Pending = 'Pending';
    case Partial = 'Partial';
    case AwaitingVerification = 'AwaitingVerification';
    case Paid = 'Paid';
    case Void = 'Void';

    public function isOpen(): bool
    {
        return in_array($this, [self::Pending, self::Partial, self::AwaitingVerification], true);
    }

    public function labelText(): string
    {
        return match ($this) {
            self::AwaitingVerification => 'Awaiting verification',
            default => $this->value,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'green',
            self::Partial => 'blue',
            self::AwaitingVerification => 'amber',
            self::Pending => 'red',
            self::Void => 'gray',
        };
    }
}
