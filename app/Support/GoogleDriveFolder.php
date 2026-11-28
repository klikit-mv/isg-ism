<?php

namespace App\Support;

final class GoogleDriveFolder
{
    /**
     * Accept a Drive folder URL or a raw id.
     */
    public static function idFrom(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('~/folders/([A-Za-z0-9_-]{10,})~', $value, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]{10,})~', $value, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{10,}$~', $value) ? $value : null;
    }

    public static function isDriveRef(?string $path): bool
    {
        return is_string($path) && str_starts_with($path, 'drive:');
    }

    public static function fileIdFromRef(?string $path): ?string
    {
        return self::isDriveRef($path) ? substr((string) $path, 6) : null;
    }
}
