<?php

namespace App\Support;

final class GoogleSlide
{
    /**
     * Accept a Google Slides link or presentation id. "local-…" ids mean the HTML layout.
     */
    public static function idFrom(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, 'local-')) {
            return $value;
        }

        if (preg_match('~/presentation/d/([A-Za-z0-9_-]{10,})~', $value, $m) || preg_match('~/d/([A-Za-z0-9_-]{10,})~', $value, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{10,}$~', $value) ? $value : null;
    }
}
