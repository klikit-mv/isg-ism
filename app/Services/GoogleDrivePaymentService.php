<?php

namespace App\Services;

use App\Services\Google\GoogleDriveClient;
use Illuminate\Support\Str;

/**
 * Payment proofs, bank deposit slips and receipts: kept in a Drive folder with one sub-folder per module.
 */
class GoogleDrivePaymentService
{
    /** Payable type => Drive sub-folder. */
    public const MODULES = [
        'class_fee' => 'Class fees',
        'annual_fee' => 'Annual fees',
        'purchase' => 'Shop purchases',
        'event_registration' => 'Events',
        'bank_deposit' => 'Bank deposits',
        'bank_expense' => 'Bank expenses',
    ];

    public function __construct(private GoogleDriveClient $drive, private SettingsService $settings) {}

    public function enabled(): bool
    {
        return $this->drive->configured() && filled($this->settings->drivePaymentsFolderId());
    }

    public function lastError(): ?string
    {
        return $this->drive->lastError();
    }

    /**
     * Create (or find) every module sub-folder under the root.
     *
     * @return array{ok: bool, message: string}
     */
    public function prepareFolders(string $rootId): array
    {
        if (! $this->drive->configured()) {
            return ['ok' => false, 'message' => 'The payments folder was saved, but Google is not connected yet. Until then proofs and slips are stored on the server.'];
        }

        if (! $this->drive->folderAccessible($rootId)) {
            return ['ok' => false, 'message' => 'The payments folder could not be opened. '.app(Google\GoogleApiClient::class)->shareHint()];
        }

        foreach (self::MODULES as $key => $name) {
            $id = $this->drive->findOrCreateFolder($name, $rootId);

            if ($id === null) {
                return ['ok' => false, 'message' => "The {$name} folder could not be created. ".$this->drive->lastError()];
            }

            $this->settings->set($this->cacheKey($key), $id);
        }

        return ['ok' => true, 'message' => 'Payments folder is ready: a sub-folder for each module was created.'];
    }

    /**
     * Store a file under the module's folder and return "drive:{id}", or null when Drive is off or refuses it.
     */
    public function put(string $module, string $filename, string $contents, string $mime): ?string
    {
        if (! $this->enabled() || ! isset(self::MODULES[$module])) {
            return null;
        }

        $name = Str::limit(preg_replace('/[^\w .()\-]+/u', '_', $filename) ?: 'file', 150, '');

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $folderId = $this->folderId($module);
            $id = $folderId ? $this->drive->upload($folderId, $name, $contents, $mime) : null;

            if ($id !== null) {
                return 'drive:'.$id;
            }

            // The cached folder may have been deleted in Drive: forget it and look it up again.
            $this->settings->forget($this->cacheKey($module));
        }

        return null;
    }

    public function contents(string $ref): ?string
    {
        return str_starts_with($ref, 'drive:') ? $this->drive->download(substr($ref, 6)) : null;
    }

    public function delete(string $ref): void
    {
        if (str_starts_with($ref, 'drive:')) {
            $this->drive->delete(substr($ref, 6));
        }
    }

    private function cacheKey(string $module): string
    {
        return 'google_drive_payments_'.$module;
    }

    private function folderId(string $module): ?string
    {
        $cached = $this->settings->get($this->cacheKey($module));

        if ($cached) {
            return $cached;
        }

        $root = $this->settings->drivePaymentsFolderId();
        $id = $root ? $this->drive->findOrCreateFolder(self::MODULES[$module], $root) : null;

        if ($id) {
            $this->settings->set($this->cacheKey($module), $id);
        }

        return $id;
    }
}
