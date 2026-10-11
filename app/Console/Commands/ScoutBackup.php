<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class ScoutBackup extends Command
{
    protected $signature = 'scout:backup {--scheduled : Only back up when the schedule in Settings says it is time}';

    protected $description = 'Back up the database to Google Drive (the schedule is set under Administration → Settings)';

    public function handle(DatabaseBackupService $backups): int
    {
        if ($this->option('scheduled') && ! $backups->isDue()) {
            return self::SUCCESS;
        }

        $result = $backups->run(null, (bool) $this->option('scheduled'));
        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
