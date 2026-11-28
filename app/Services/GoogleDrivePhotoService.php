<?php

namespace App\Services;

use App\Models\Student;
use App\Services\Google\GoogleDriveClient;
use App\Support\GoogleDriveFolder;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos and images: stored in Drive area folders when configured, else the public disk.
 */
class GoogleDrivePhotoService
{
    public const AREAS = ['students' => 'Students', 'shop' => 'Shop', 'badges' => 'Badges'];

    public const LOCAL_DIRS = ['students' => 'students', 'shop' => 'shop-items', 'badges' => 'badges'];

    public function __construct(private GoogleDriveClient $drive, private SettingsService $settings) {}

    /**
     * Create or find the area sub-folders under the photos folder.
     *
     * @return array{ok: bool, message: string}
     */
    public function ensureAreaFolders(?string $rootId = null): array
    {
        $rootId ??= $this->settings->driveFolderId();

        if (! $this->drive->configured()) {
            return ['ok' => false, 'message' => 'The folder was saved, but Google is not connected yet: upload the service account key first. Until then photos are stored on the server.'];
        }

        if (! $rootId || ! $this->drive->folderAccessible($rootId)) {
            return ['ok' => false, 'message' => 'The Drive folder could not be opened. '.app(Google\GoogleApiClient::class)->shareHint()];
        }

        foreach (self::AREAS as $key => $name) {
            $id = $this->drive->findOrCreateFolder($name, $rootId);

            if ($id === null) {
                return ['ok' => false, 'message' => "The {$name} folder could not be created."];
            }

            $this->settings->set('google_drive_folder_'.$key, $id);
        }

        return ['ok' => true, 'message' => 'Drive is ready. Photos will be stored in the Students, Shop and Badges folders.'];
    }

    public function areaFolderId(string $area): ?string
    {
        return $this->drive->configured() ? $this->settings->driveAreaFolderId($area) : null;
    }

    /**
     * Store an image and return its path: "drive:{id}" or a public-disk path.
     */
    public function store(UploadedFile $file, string $area): string
    {
        $extension = strtolower($file->extension() ?: 'jpg');
        $name = Str::uuid().'.'.$extension;
        $folderId = $this->areaFolderId($area);

        if ($folderId !== null) {
            $id = $this->drive->upload($folderId, $name, (string) $file->get(), (string) $file->getMimeType());

            if ($id !== null) {
                return 'drive:'.$id;
            }

            // Keep the picture on the server, but say why it did not reach Drive.
            session()->flash('warning', 'The picture was saved on the server, not in Google Drive. '.$this->drive->lastError());
        }

        return Uploads::store($file, self::LOCAL_DIRS[$area] ?? $area, $name, 'public');
    }

    public function assignStudentPhoto(Student $student, UploadedFile $file): void
    {
        $old = $student->photo_path;
        $student->update(['photo_path' => $this->store($file, 'students')]);
        $this->delete($old);
    }

    public function clearStudentPhoto(Student $student): void
    {
        $old = $student->photo_path;
        $student->update(['photo_path' => null]);
        $this->delete($old);
    }

    public function url(?string $path): ?string
    {
        return photo_url($path);
    }

    /**
     * Raw bytes of a stored image.
     */
    public function contents(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (GoogleDriveFolder::isDriveRef($path)) {
            return $this->drive->download((string) GoogleDriveFolder::fileIdFromRef($path));
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->get($path) : null;
    }

    /**
     * @return array{contents: string, mime: string}|null
     */
    public function payload(string $fileId): ?array
    {
        $contents = $this->drive->download($fileId);

        if ($contents === null) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        return ['contents' => $contents, 'mime' => $mime];
    }

    public function download(string $fileId): ?string
    {
        return $this->drive->download($fileId);
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

        Storage::disk('public')->delete($path);
    }
}
