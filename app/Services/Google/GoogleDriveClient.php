<?php

namespace App\Services\Google;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minimal Drive v3 client over plain HTTP. Every call fails soft.
 */
class GoogleDriveClient
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3/files';

    private ?string $lastError = null;

    public function __construct(private GoogleApiClient $google) {}

    /**
     * Why the last call failed, in plain words (null when it did not fail).
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Log a refused Drive call and keep a readable reason.
     */
    private function failed(string $action, ?Response $response): void
    {
        $reason = (string) $response?->json('error.errors.0.reason');
        $message = (string) ($response?->json('error.message') ?? '');

        $this->lastError = match (true) {
            $response === null => "{$action}: Google is not connected or could not be reached.",
            $reason === 'storageQuotaExceeded' => "{$action}: Google says this account has no storage. A service account cannot keep files in a normal Drive folder: connect a Google account in Settings instead, or use a folder in a shared drive.",
            in_array($response->status(), [401, 403], true) && str_contains($message, 'insufficient') => "{$action}: the connected Google account did not give permission to manage Drive files. Disconnect and connect it again, and tick every permission.",
            in_array($response->status(), [403, 404], true) => "{$action}: Google refused (folder missing or not shared with the connected account). {$message}",
            default => "{$action}: Google replied {$response->status()}. {$message}",
        };

        Log::warning('Drive call failed', ['action' => $action, 'status' => $response?->status(), 'reason' => $reason, 'message' => $message]);
    }

    public function configured(): bool
    {
        return $this->google->configured();
    }

    public function findOrCreateFolder(string $name, string $parentId): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $query = "name = '{$escaped}' and '{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false";

        $found = $this->request()?->get(self::API.'/files', [
            'q' => $query,
            'fields' => 'files(id,name)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
        ]);

        if ($found?->successful() && ($id = $found->json('files.0.id'))) {
            return $id;
        }

        $created = $this->request()?->post(self::API.'/files?supportsAllDrives=true', [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parentId],
        ]);

        if (! $created?->successful()) {
            $this->failed('Creating the Drive folder', $created);

            return null;
        }

        return $created->json('id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fileInfo(string $fileId): ?array
    {
        $response = $this->request()?->get(self::API.'/files/'.$fileId, [
            'fields' => 'id,name,mimeType,trashed',
            'supportsAllDrives' => 'true',
        ]);

        return $response?->successful() ? $response->json() : null;
    }

    public function folderAccessible(string $folderId): bool
    {
        $info = $this->fileInfo($folderId);

        return $info !== null && ($info['mimeType'] ?? '') === 'application/vnd.google-apps.folder' && ! ($info['trashed'] ?? false);
    }

    public function upload(string $folderId, string $name, string $contents, string $mime): ?string
    {
        try {
            $boundary = 'scout'.bin2hex(random_bytes(8));
            $metadata = json_encode(['name' => $name, 'parents' => [$folderId]]);
            $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
                ."--{$boundary}\r\nContent-Type: {$mime}\r\n\r\n{$contents}\r\n--{$boundary}--";

            $response = $this->request()?->withBody($body, 'multipart/related; boundary='.$boundary)
                ->post(self::UPLOAD.'?uploadType=multipart&supportsAllDrives=true&fields=id');

            if (! $response?->successful()) {
                $this->failed('Uploading to Drive', $response);

                return null;
            }

            $this->lastError = null;

            return $response->json('id');
        } catch (Throwable $e) {
            Log::warning('Drive upload failed', ['error' => $e->getMessage()]);
            $this->lastError = 'Uploading to Drive: '.$e->getMessage();

            return null;
        }
    }

    public function download(string $fileId): ?string
    {
        $response = $this->request()?->get(self::API.'/files/'.$fileId, ['alt' => 'media', 'supportsAllDrives' => 'true']);

        return $response?->successful() ? $response->body() : null;
    }

    public function delete(string $fileId): bool
    {
        $response = $this->request()?->delete(self::API.'/files/'.$fileId.'?supportsAllDrives=true');

        return (bool) $response?->successful();
    }

    /**
     * An authorised request, or null when Google is not configured or unreachable.
     */
    public function request(): ?PendingRequest
    {
        $token = $this->google->accessToken();

        if ($token === null) {
            return null;
        }

        return Http::withToken($token)->timeout(40);
    }
}
