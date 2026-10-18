<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ParentLinkStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Inactive = 'inactive';

    public function grantsAccess(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Whether this link occupies the scout's single parent slot.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }

    public function tone(): string
    {
        return match ($this) {
            self::Approved => 'green',
            self::Pending => 'amber',
            self::Rejected => 'red',
            self::Inactive => 'gray',
        };
    }
}
