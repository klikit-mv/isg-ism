<?php

namespace App\Http\Controllers;

use App\Services\GoogleDrivePhotoService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves Drive-hosted photos to signed-in users.
 */
class PhotoController extends Controller
{
    public function show(string $fileId, GoogleDrivePhotoService $photos): Response
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{10,}$/', $fileId) === 1, 404);

        $payload = $photos->payload($fileId);
        abort_if($payload === null || ! str_starts_with($payload['mime'], 'image/'), 404);

        return response($payload['contents'], 200, [
            'Content-Type' => $payload['mime'],
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
