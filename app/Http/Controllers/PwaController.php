<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Makes the portal installable on Android and iOS (home-screen app): manifest, icons and the offline page.
 */
class PwaController extends Controller
{
    public const SIZES = [180, 192, 512];

    public function manifest(SettingsService $settings): JsonResponse
    {
        $version = $settings->logoPath() ? substr(md5((string) $settings->logoPath()), 0, 8) : '0';

        return response()->json([
            'name' => config('scout.name'),
            'short_name' => mb_substr((string) config('scout.short_name'), 0, 12) ?: 'Scouts',
            'description' => 'Scout management for '.config('scout.name'),
            'start_url' => '/dashboard',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#ffffff',
            'theme_color' => '#1e3a8a',
            'icons' => [
                ['src' => route('pwa.icon', ['size' => 192, 'v' => $version], false), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', ['size' => 512, 'v' => $version], false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => route('pwa.icon', ['size' => 512, 'v' => $version], false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json')->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * Square PNG app icon: the uploaded logo centred on a navy tile, or a built-in emblem when there is none.
     */
    public function icon(SettingsService $settings, int $size): Response
    {
        abort_unless(in_array($size, self::SIZES, true), 404);

        $canvas = imagecreatetruecolor($size, $size);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 30, 58, 138));

        $logo = null;
        $path = $settings->logoPath();

        if ($path !== null) {
            $logo = @imagecreatefromstring((string) Storage::disk('public')->get($path)) ?: null;
        }

        if ($logo !== null) {
            $inner = (int) round($size * 0.7);
            $ratio = min($inner / imagesx($logo), $inner / imagesy($logo));
            $w = max(1, (int) round(imagesx($logo) * $ratio));
            $h = max(1, (int) round(imagesy($logo) * $ratio));
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $logo, intdiv($size - $w, 2), intdiv($size - $h, 2), 0, 0, $w, $h, imagesx($logo), imagesy($logo));
        } else {
            $gold = imagecolorallocate($canvas, 251, 191, 36);
            $half = $size / 2;
            imagefilledpolygon($canvas, [(int) $half, (int) ($size * 0.18), (int) ($size * 0.68), (int) ($size * 0.58), (int) ($size * 0.56), (int) ($size * 0.82), (int) ($size * 0.44), (int) ($size * 0.82), (int) ($size * 0.32), (int) ($size * 0.58)], $gold);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    public function offline(): Response
    {
        return response()->view('offline')->header('Cache-Control', 'public, max-age=3600');
    }
}
