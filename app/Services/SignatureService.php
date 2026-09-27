<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Leader and admin signature images used when signing certificates.
 */
class SignatureService
{
    public const DISK = 'signatures';

    public function storeUpload(User $user, UploadedFile $file): string
    {
        $old = $user->signature_path;
        $path = $file->storeAs('', $user->uuid.'-'.Str::random(6).'.'.strtolower($file->extension() ?: 'png'), self::DISK);

        $user->forceFill(['signature_path' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk(self::DISK)->delete($old);
        }

        return $path;
    }

    public function dataUri(?User $user): ?string
    {
        if ($user === null || ! $user->signature_path || ! Storage::disk(self::DISK)->exists($user->signature_path)) {
            return null;
        }

        $contents = Storage::disk(self::DISK)->get($user->signature_path);
        $mime = str_ends_with(strtolower($user->signature_path), '.png') ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode((string) $contents);
    }

    /**
     * A drawn-name signature for signers with no uploaded image.
     */
    public function fromName(string $name): string
    {
        $safe = htmlspecialchars($name, ENT_QUOTES);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="90"><text x="10" y="60" font-family="DejaVu Serif, serif" font-style="italic" font-size="36" fill="#1e1b4b">'.$safe.'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function blank(): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"></svg>');
    }
}
