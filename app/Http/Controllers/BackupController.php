<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DatabaseBackupService;
use App\Services\Google\GoogleApiClient;
use App\Services\Google\GoogleDriveClient;
use App\Services\SettingsService;
use App\Support\GoogleDriveFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'google_drive_backup_folder' => ['nullable', 'string', 'max:500'],
            'enabled' => ['nullable', 'boolean'],
            'frequency' => ['required', Rule::in(array_keys(DatabaseBackupService::FREQUENCIES))],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'monthday' => ['required', 'integer', 'between:1,31'],
            'keep' => ['required', 'integer', 'between:0,365'],
        ], ['time.regex' => 'Enter the time as hours and minutes, for example 02:00.']);

        $folderMessage = $this->saveFolder($request->input('google_drive_backup_folder'), $request->user(), (bool) $request->boolean('enabled'));

        $this->backups->saveSchedule([
            'enabled' => $request->boolean('enabled'),
            'frequency' => $data['frequency'],
            'time' => $data['time'],
            'weekday' => (int) $data['weekday'],
            'monthday' => (int) $data['monthday'],
            'keep' => (int) $data['keep'],
        ], $request->user());

        $saved = $request->boolean('enabled')
            ? 'Backup schedule saved. Next backup: '.scout_datetime($this->backups->nextSlot(now())).' (Maldives time).'
            : 'Backup schedule saved. Automatic backups are switched off.';

        return back()->with('success', trim($saved.' '.$folderMessage));
    }

    /**
     * The backup folder is its own setting (never inside the shared photo or certificate folders).
     */
    private function saveFolder(?string $input, User $actor, bool $required): string
    {
        $settings = app(SettingsService::class);

        if (blank($input)) {
            if ($required) {
                throw ValidationException::withMessages(['google_drive_backup_folder' => 'Paste the link of a private Google Drive folder for the backups.']);
            }

            $settings->set('google_drive_backup_folder', null, $actor);
            $settings->set('google_drive_backup_folder_id', null, $actor);

            return '';
        }

        $id = GoogleDriveFolder::idFrom($input);

        if ($id === null) {
            throw ValidationException::withMessages(['google_drive_backup_folder' => 'Enter a Google Drive folder link or folder ID.']);
        }

        $others = array_filter([$settings->driveFolderId(), $settings->get('google_drive_certificates_folder_id'), $settings->drivePaymentsFolderId()]);

        if (in_array($id, $others, true)) {
            throw ValidationException::withMessages(['google_drive_backup_folder' => 'Use a different folder: the backup folder must not be one of the photo, certificate or payment folders, so it can stay private.']);
        }

        $settings->set('google_drive_backup_folder', trim($input), $actor);
        $settings->set('google_drive_backup_folder_id', $id, $actor);
        $drive = app(GoogleDriveClient::class);

        if (! $drive->configured()) {
            return 'The folder was saved; connect Google Drive for backups to run.';
        }

        return $drive->folderAccessible($id)
            ? 'The backup folder is ready.'
            : 'The backup folder could not be opened. '.app(GoogleApiClient::class)->shareHint();
    }

    public function run(Request $request): RedirectResponse
    {
        $result = $this->backups->run($request->user());

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
