<?php

namespace App\Services;

use App\Services\Google\GoogleApiClient;
use App\Services\Google\GoogleDriveClient;
use App\Services\Google\GoogleSlideExporter;

/**
 * Plain-language reachability checks for the Settings test buttons.
 */
class ConnectionTestService
{
    public function __construct(
        private GoogleApiClient $google,
        private GoogleDriveClient $drive,
        private GoogleSlideExporter $slides,
        private SettingsService $settings,
        private TelegramService $telegram,
    ) {}

    /**
     * @return array{ok: bool, message: string}
     */
    public function googleDrive(): array
    {
        if (! $this->google->configured()) {
            return ['ok' => false, 'message' => 'Google is not set up yet: connect a Google account (or upload a service account key) under Settings → Google Drive. Until then photos and certificates are stored on the server.'];
        }

        if ($this->google->accessToken() === null) {
            return ['ok' => false, 'message' => ($this->google->usesOauth() ? 'Google rejected the connected account. Disconnect and connect it again.' : 'Google rejected the service account credentials.')];
        }

        $messages = [];
        $ok = true;

        foreach (['Photos' => $this->settings->driveFolderId(), 'Certificates' => $this->settings->driveCertificatesFolderId()] as $label => $folderId) {
            if (! $folderId) {
                $messages[] = "{$label} folder: not set.";

                continue;
            }

            if ($this->drive->folderAccessible($folderId)) {
                $messages[] = "{$label} folder: ready.";
            } else {
                $ok = false;
                $messages[] = "{$label} folder: cannot be opened. ".$this->google->shareHint();
            }
        }

        if ($ok) {
            // Folders can be readable while uploads are still refused (no storage, read-only access).
            $folderId = $this->settings->driveFolderId() ?: $this->settings->driveCertificatesFolderId();
            $id = $folderId ? $this->drive->upload($folderId, 'scout-connection-test.txt', 'Upload test', 'text/plain') : null;

            if ($id === null) {
                return ['ok' => false, 'message' => 'Google Drive is reachable, but a test upload failed. '.($this->drive->lastError() ?? '')];
            }

            $this->drive->delete($id);
            $messages[] = 'Test upload: worked.';
        }

        return ['ok' => $ok, 'message' => 'Google Drive is reachable. '.implode(' ', $messages)];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function googleSlides(string $presentationId): array
    {
        return $this->slides->testPresentation($presentationId);
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function telegram(): array
    {
        return $this->telegram->testConnection();
    }
}
