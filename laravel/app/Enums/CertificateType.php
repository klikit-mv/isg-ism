<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum CertificateType: string
{
    use HasOptions;

    case Badge = 'badge';
    case General = 'general';
    case Leadership = 'leadership';

    public function tone(): string
    {
        return match ($this) {
            self::Badge => 'green',
            self::General => 'blue',
            self::Leadership => 'purple',
        };
    }
}
