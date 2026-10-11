<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'frequency' => ['required', Rule::in(array_keys(DatabaseBackupService::FREQUENCIES))],
            'time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'monthday' => ['required', 'integer', 'between:1,31'],
            'keep' => ['required', 'integer', 'between:0,365'],
        ], ['time.regex' => 'Enter the time as hours and minutes, for example 02:00.']);

        $this->backups->saveSchedule([
            'enabled' => $request->boolean('enabled'),
            'frequency' => $data['frequency'],
            'time' => $data['time'],
            'weekday' => (int) $data['weekday'],
            'monthday' => (int) $data['monthday'],
            'keep' => (int) $data['keep'],
        ], $request->user());

        return back()->with('success', $request->boolean('enabled')
            ? 'Backup schedule saved. Next backup: '.scout_datetime($this->backups->nextSlot(now())).' (Maldives time).'
            : 'Backup schedule saved. Automatic backups are switched off.');
    }

    public function run(Request $request): RedirectResponse
    {
        $result = $this->backups->run($request->user());

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
