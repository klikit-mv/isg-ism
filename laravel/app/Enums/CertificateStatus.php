<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CertificateStatus: string
{
    use HasOptions;

    case Issued = 'issued';
    case Verified = 'verified';

    public function tone(): string
    {
        return $this === self::Verified ? 'green' : 'blue';
    }
}
