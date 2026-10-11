<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ScoutCleanup extends Command
{
    protected $signature = 'scout:cleanup';

    protected $description = 'Clear the settings cache, prune expired sessions and stale temporary files';

    public function handle(SettingsService $settings): int
    {
        $settings->flush();

        $expired = 0;

        if (config('session.driver') === 'database') {
            $expired = DB::table(config('session.table', 'sessions'))->where('last_activity', '<', now()->subMinutes((int) config('session.lifetime'))->getTimestamp())->delete();
        }

        $removed = 0;

        foreach (['imports', 'exports'] as $directory) {
            foreach (Storage::disk('local')->files($directory) as $file) {
                if (Storage::disk('local')->lastModified($file) < now()->subDay()->getTimestamp()) {
                    Storage::disk('local')->delete($file);
                    $removed++;
                }
            }
        }

        $this->info("Settings cache cleared, {$expired} expired session(s) and {$removed} stale temporary file(s) removed.");

        return self::SUCCESS;
    }
}
