<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copy a Slides template, replace placeholders, export PDF, delete the copy.
 */
class GoogleSlideExporter
{
    public function __construct(private GoogleDriveClient $drive) {}

    /**
     * @param  array<string, string>  $replacements  placeholder => text
     */
    public function exportPdf(string $presentationId, array $replacements): ?string
    {
        $request = $this->drive->request();

        if ($request === null) {
            return null;
        }

        $copyId = null;

        try {
            $copy = $request->post("https://www.googleapis.com/drive/v3/files/{$presentationId}/copy?supportsAllDrives=true", [
                'name' => 'certificate-'.bin2hex(random_bytes(4)),
            ]);

            if (! $copy->successful()) {
                return null;
            }

            $copyId = $copy->json('id');
            $requests = [];

            foreach ($replacements as $placeholder => $text) {
                $requests[] = ['replaceAllText' => [
                    'containsText' => ['text' => $placeholder, 'matchCase' => true],
                    'replaceText' => $text,
                ]];
            }

            if ($requests !== []) {
                $update = $this->drive->request()?->post("https://slides.googleapis.com/v1/presentations/{$copyId}:batchUpdate", ['requests' => $requests]);

                if (! $update?->successful()) {
                    return null;
                }
            }

            $pdf = $this->drive->request()?->get("https://www.googleapis.com/drive/v3/files/{$copyId}/export", ['mimeType' => 'application/pdf']);

            return $pdf?->successful() ? $pdf->body() : null;
        } catch (Throwable $e) {
            Log::warning('Slides export failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            if ($copyId) {
                $this->drive->delete($copyId);
            }
        }
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function testPresentation(string $presentationId): array
    {
        if (! $this->drive->configured()) {
            return ['ok' => false, 'message' => 'Google credentials are not configured on the server.'];
        }

        $info = $this->drive->fileInfo($presentationId);

        if ($info === null) {
            return ['ok' => false, 'message' => 'The file could not be opened. Share it with the service account as an editor.'];
        }

        if (($info['mimeType'] ?? '') !== 'application/vnd.google-apps.presentation') {
            return ['ok' => false, 'message' => 'That file is not a Google Slides presentation.'];
        }

        return ['ok' => true, 'message' => 'The presentation “'.($info['name'] ?? $presentationId).'” is reachable.'];
    }
}
