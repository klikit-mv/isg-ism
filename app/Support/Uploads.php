<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Stores uploads by their temporary pathname rather than realpath(), which
 * can be empty on some Windows setups (e.g. Laravel Herd) and makes
 * UploadedFile::storeAs() fail with "Path must not be empty".
 */
final class Uploads
{
    public static function store(UploadedFile $file, string $directory, string $name, string $disk): string
    {
        $source = self::path($file);
        $stream = @fopen($source, 'r');

        if ($stream === false) {
            throw new RuntimeException('The uploaded file could not be read. Please try again.');
        }

        $path = ltrim(trim($directory, '/').'/'.$name, '/');

        try {
            Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Pixel size of an uploaded image, or null when it cannot be read.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function imageSize(UploadedFile $file): ?array
    {
        $size = @getimagesize(self::path($file));

        return $size === false ? null : [(int) $size[0], (int) $size[1]];
    }

    private static function path(UploadedFile $file): string
    {
        return (string) ($file->getRealPath() ?: $file->getPathname());
    }
}
