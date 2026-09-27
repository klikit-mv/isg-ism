<?php

namespace App\Services;

use App\Models\Student;
use App\Services\Google\GoogleDriveClient;
use App\Support\GoogleDriveFolder;
use Illuminate\Support\Facades\Storage;

/**
 * Certificate PDF storage: Drive per-scout folders when configured, else the certificates disk.
 */
class GoogleDriveCertificateService
{
    public const DISK = 'certificates';

    public function __construct(private GoogleDriveClient $drive, private SettingsService $settings) {}

    public function driveEnabled(): bool
    {
        return $this->drive->configured() && filled($this->settings->driveCertificatesFolderId());
    }

    /**
     * Store a PDF and return its path; a replaced file is deleted.
     */
    public function put(Student $student, string $certNumber, string $pdf, ?string $oldPath = null): string
    {
        $path = null;

        if ($this->driveEnabled()) {
            $folderId = $this->studentFolderId($student);
            $id = $folderId ? $this->drive->upload($folderId, $certNumber.'.pdf', $pdf, 'application/pdf') : null;

            if ($id === null && $folderId !== null) {
                // The cached folder may have been removed: recreate once.
                $this->settings->forget('google_drive_certificates_student_'.$student->id);
                $folderId = $this->studentFolderId($student);
                $id = $folderId ? $this->drive->upload($folderId, $certNumber.'.pdf', $pdf, 'application/pdf') : null;
            }

            if ($id !== null) {
                $path = 'drive:'.$id;
            }
        }

        if ($path === null) {
            $path = $student->id.'/'.$certNumber.'.pdf';
            Storage::disk(self::DISK)->put($path, $pdf);
        }

        if ($oldPath !== null && $oldPath !== $path) {
            $this->delete($oldPath);
        }

        return $path;
    }

    public function contents(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (GoogleDriveFolder::isDriveRef($path)) {
            return $this->drive->download((string) GoogleDriveFolder::fileIdFromRef($path));
        }

        return Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->get($path) : null;
    }

    public function exists(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        if (GoogleDriveFolder::isDriveRef($path)) {
            return $this->drive->fileInfo((string) GoogleDriveFolder::fileIdFromRef($path)) !== null;
        }

        return Storage::disk(self::DISK)->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        if (GoogleDriveFolder::isDriveRef($path)) {
            $this->drive->delete((string) GoogleDriveFolder::fileIdFromRef($path));

            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    public function studentFolderName(Student $student): string
    {
        return $student->index_number.' — '.$student->name;
    }

    public function studentFolderId(Student $student): ?string
    {
        $key = 'google_drive_certificates_student_'.$student->id;
        $cached = $this->settings->get($key);

        if ($cached) {
            return $cached;
        }

        $root = $this->settings->driveCertificatesFolderId();
        $id = $root ? $this->drive->findOrCreateFolder($this->studentFolderName($student), $root) : null;

        if ($id) {
            $this->settings->set($key, $id);
        }

        return $id;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function verifyRootFolder(string $folderId): array
    {
        if (! $this->drive->configured()) {
            return ['ok' => false, 'message' => 'Google credentials are not configured, so certificates are stored on the server.'];
        }

        return $this->drive->folderAccessible($folderId)
            ? ['ok' => true, 'message' => 'The certificates folder is ready.']
            : ['ok' => false, 'message' => 'The certificates folder could not be opened. Share it with the service account as an editor.'];
    }
}
