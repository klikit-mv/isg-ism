<?php

namespace App\Services\Google;

use App\Services\SettingsService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copy a Slides template, replace placeholders, export PDF, delete the copy.
 */
class GoogleSlideExporter
{
    private ?string $lastError = null;

    public function __construct(private GoogleDriveClient $drive, private SettingsService $settings) {}

    /**
     * Why the last export failed, in plain words.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function fail(string $step, ?Response $response): ?string
    {
        $message = (string) ($response?->json('error.message') ?? '');
        $reason = (string) $response?->json('error.errors.0.reason');

        $this->lastError = match (true) {
            $response === null => "{$step}: Google is not connected or could not be reached.",
            $reason === 'storageQuotaExceeded' => "{$step}: Google says this account has no storage for the working copy. Connect a Google account in Settings, or put the certificates folder in a shared drive.",
            $response->status() === 403 && str_contains($message, 'has not been used') => "{$step}: the Google Slides API is switched off. Enable it in Google Cloud Console (APIs & Services → Google Slides API).",
            default => "{$step}: Google replied {$response->status()}. {$message}",
        };
        Log::warning('Slides export failed', ['step' => $step, 'status' => $response?->status(), 'message' => $message, 'reason' => $reason]);

        return null;
    }

    /**
     * @param  array<string, string>  $replacements  placeholder => text
     */
    public function exportPdf(string $presentationId, array $replacements): ?string
    {
        $this->lastError = null;
        $request = $this->drive->request();

        if ($request === null) {
            $this->lastError = 'Google is not connected.';

            return null;
        }

        $copyId = null;

        try {
            $parent = $this->settings->driveCertificatesFolderId();
            $copy = $request->post("https://www.googleapis.com/drive/v3/files/{$presentationId}/copy?supportsAllDrives=true", array_filter([
                'name' => 'certificate-'.bin2hex(random_bytes(4)),
                'parents' => $parent ? [$parent] : null,
            ]));

            if (! $copy->successful()) {
                return $this->fail('Copying the Slides template', $copy);
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
                    return $this->fail('Filling in the Slides template', $update);
                }
            }

            $pdf = $this->drive->request()?->get("https://www.googleapis.com/drive/v3/files/{$copyId}/export", ['mimeType' => 'application/pdf']);

            return $pdf?->successful() ? $pdf->body() : $this->fail('Exporting the Slides PDF', $pdf);
        } catch (Throwable $e) {
            Log::warning('Slides export failed', ['error' => $e->getMessage()]);
            $this->lastError = 'Slides export: '.$e->getMessage();

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
            return ['ok' => false, 'message' => 'Google is not connected yet. Upload the service account key under Administration → Settings → Google Drive.'];
        }

        $info = $this->drive->fileInfo($presentationId);

        if ($info === null) {
            return ['ok' => false, 'message' => 'The file could not be opened. '.app(GoogleApiClient::class)->shareHint()];
        }

        if (($info['mimeType'] ?? '') !== 'application/vnd.google-apps.presentation') {
            return ['ok' => false, 'message' => 'That file is not a Google Slides presentation.'];
        }

        // A real trial run: copy, fill in, export, delete. This is what fails when certificates fall back to the built-in layout.
        $pdf = $this->exportPdf($presentationId, ['{{name}}' => 'Test']);

        if ($pdf === null || ! str_starts_with($pdf, '%PDF')) {
            return ['ok' => false, 'message' => 'The presentation “'.($info['name'] ?? $presentationId).'” opens, but making a PDF from it failed. '.($this->lastError ?? '')];
        }

        return ['ok' => true, 'message' => 'The presentation “'.($info['name'] ?? $presentationId).'” works: a test PDF was made from it.'];
    }
}
