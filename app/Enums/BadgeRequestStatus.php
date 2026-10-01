<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BadgeRequestStatus: string
{
    use HasOptions;

    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Generated = 'generated';

    /**
     * Whether this request blocks another request for the same badge.
     */
    public function blocksDuplicate(): bool
    {
        return $this !== self::Rejected;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'amber',
            self::Approved => 'blue',
            self::Rejected => 'red',
            self::Generated => 'green',
        };
    }
}
