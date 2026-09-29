<?php

namespace App\Support;

final class Pagination
{
    public const MAX = 20;

    public static function perPage(?int $requested = null): int
    {
        return max(1, min(self::MAX, $requested ?? self::MAX));
    }
}
