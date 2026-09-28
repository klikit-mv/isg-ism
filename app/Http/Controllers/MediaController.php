<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves images from the public disk through the app, so they work without
 * `storage:link` and whatever APP_URL is set to.
 */
class MediaController extends Controller
{
    /**
     * Folders on the public disk that may be served to signed-in users.
     */
    private const DIRECTORIES = ['students', 'shop-items', 'badges'];

    /**
     * The website logo (public: it appears on the sign-in page).
     */
    public function logo(SettingsService $settings): Response
    {
        $path = $settings->logoPath();
        abort_if($path === null, 404);

        return $this->image($path, 'public, max-age=86400');
    }

    public function show(string $path): Response
    {
        $directory = strstr($path, '/', true);

        abort_unless(
            in_array($directory, self::DIRECTORIES, true)
            && preg_match('#^[a-z-]+/[A-Za-z0-9._-]+$#', $path) === 1
            && ! str_contains($path, '..')
            && Storage::disk('public')->exists($path),
            404,
        );

        return $this->image($path, 'private, max-age=3600');
    }

    private function image(string $path, string $cacheControl): Response
    {
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';
        abort_unless(str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml', 404);

        return Storage::disk('public')->response($path, basename($path), [
            'Content-Type' => $mime,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
