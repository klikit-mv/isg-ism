<?php

namespace App\Services;

use App\Enums\Role;
use App\Exceptions\ScoutException;
use App\Models\User;
use App\Services\Google\GoogleDriveClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Database backups: a compressed SQL dump written in plain PHP (no mysqldump needed, so it works on shared hosting),
 * uploaded to a "Database backups" folder inside the main Google Drive folder on a daily, weekly or monthly schedule
 * set in Settings. Times are Maldives time.
 */
class DatabaseBackupService
{
    public const FOLDER_NAME = 'Database backups';

    public const FREQUENCIES = ['daily' => 'Every day', 'weekly' => 'Every week', 'monthly' => 'Every month'];

    public const WEEKDAYS = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'];

    private const SKIP_TABLES = ['sessions', 'cache', 'cache_locks'];

    private const RETRY_MINUTES = 30;

    private const MAX_ATTEMPTS_PER_SLOT = 3;

    public function __construct(private SettingsService $settings, private GoogleDriveClient $drive, private AuditLogService $audit, private NotificationService $notifications) {}

    /**
     * @return array{enabled: bool, frequency: string, time: string, weekday: int, monthday: int, keep: int}
     */
    public function schedule(): array
    {
        return [
            'enabled' => $this->settings->get('backup_enabled') === '1',
            'frequency' => array_key_exists((string) $this->settings->get('backup_frequency'), self::FREQUENCIES) ? (string) $this->settings->get('backup_frequency') : 'daily',
            'time' => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $this->settings->get('backup_time')) ? (string) $this->settings->get('backup_time') : '02:00',
            'weekday' => (int) $this->settings->get('backup_weekday', '5'),
            'monthday' => max(1, min(31, (int) $this->settings->get('backup_monthday', '1'))),
            'keep' => max(0, (int) $this->settings->get('backup_keep', '14')),
        ];
    }

    /**
     * @param  array{enabled: bool, frequency: string, time: string, weekday: int, monthday: int, keep: int}  $data
     */
    public function saveSchedule(array $data, User $actor): void
    {
        $wasEnabled = $this->settings->get('backup_enabled') === '1';

        foreach (['frequency', 'time', 'weekday', 'monthday', 'keep'] as $key) {
            $this->settings->set('backup_'.$key, (string) $data[$key], $actor);
        }

        $this->settings->set('backup_enabled', $data['enabled'] ? '1' : '0', $actor);

        if ($data['enabled'] && ! $wasEnabled) {
            // The first backup waits for the next scheduled time instead of running the moment this is switched on.
            $this->settings->set('backup_enabled_at', now()->toIso8601String(), $actor);
        }

        $this->audit->record('backup.schedule_saved', null, ['enabled' => $data['enabled'], 'frequency' => $data['frequency'], 'time' => $data['time']], $actor);
    }

    /**
     * The most recent scheduled moment that is not in the future.
     */
    public function latestSlot(CarbonInterface $now): Carbon
    {
        $schedule = $this->schedule();
        [$hour, $minute] = array_map('intval', explode(':', $schedule['time']));
        $now = Carbon::instance($now)->setTimezone(config('scout.timezone'));

        if ($schedule['frequency'] === 'monthly') {
            $slot = $this->monthlySlot($now, $schedule['monthday'], $hour, $minute);

            return $slot->greaterThan($now) ? $this->monthlySlot($now->copy()->startOfMonth()->subMonth(), $schedule['monthday'], $hour, $minute) : $slot;
        }

        $slot = $now->copy()->setTime($hour, $minute);

        for ($i = 0; $i < 8; $i++) {
            if ($slot->lessThanOrEqualTo($now) && ($schedule['frequency'] === 'daily' || $slot->dayOfWeek === $schedule['weekday'])) {
                return $slot;
            }

            $slot->subDay();
        }

        return $slot;
    }

    private function monthlySlot(Carbon $inMonth, int $day, int $hour, int $minute): Carbon
    {
        return $inMonth->copy()->startOfMonth()->setDay(min($day, $inMonth->daysInMonth))->setTime($hour, $minute);
    }

    public function nextSlot(CarbonInterface $now): Carbon
    {
        $now = Carbon::instance($now)->setTimezone(config('scout.timezone'));
        $probe = $this->latestSlot($now);
        $schedule = $this->schedule();

        return match ($schedule['frequency']) {
            'daily' => $probe->copy()->addDay(),
            'weekly' => $probe->copy()->addWeek(),
            default => $this->monthlySlot($probe->copy()->startOfMonth()->addMonth(), $schedule['monthday'], $probe->hour, $probe->minute),
        };
    }

    public function isDue(?CarbonInterface $now = null): bool
    {
        if (! $this->schedule()['enabled']) {
            return false;
        }

        $now = Carbon::instance($now ?? now())->setTimezone(config('scout.timezone'));
        $slot = $this->latestSlot($now);
        $lastSuccess = $this->time('backup_last_success_at') ?? $this->time('backup_enabled_at') ?? $now;

        if ($lastSuccess->greaterThanOrEqualTo($slot)) {
            return false;
        }

        $lastAttempt = $this->time('backup_last_attempt_at');

        if ($lastAttempt === null || $lastAttempt->lessThan($slot)) {
            return true;
        }

        return (int) $this->settings->get('backup_attempts', '0') < self::MAX_ATTEMPTS_PER_SLOT
            && $lastAttempt->lessThanOrEqualTo($now->copy()->subMinutes(self::RETRY_MINUTES));
    }

    /**
     * Make the dump, upload it, tidy old copies and record the outcome. Never throws.
     *
     * @return array{ok: bool, message: string, file: ?string}
     */
    public function run(?User $actor = null, bool $scheduled = false): array
    {
        $now = now();
        $slot = $this->latestSlot($now);
        $lastAttempt = $this->time('backup_last_attempt_at');
        $attempts = $lastAttempt !== null && $lastAttempt->greaterThanOrEqualTo($slot) ? (int) $this->settings->get('backup_attempts', '0') + 1 : 1;
        $this->settings->set('backup_last_attempt_at', $now->toIso8601String());
        $this->settings->set('backup_attempts', (string) $attempts);

        $path = null;

        try {
            $folderId = $this->folderId();
            $path = $this->createDump();
            $name = 'scout-backup-'.scout_now()->format('Y-m-d-Hi').'.sql.gz';
            $id = $this->drive->upload($folderId, $name, (string) file_get_contents($path), 'application/gzip');

            if ($id === null) {
                throw new ScoutException('The backup could not be uploaded. '.$this->drive->lastError());
            }

            $removed = $this->prune($folderId);
            $size = (int) filesize($path);
            $message = "Backup {$name} uploaded to Google Drive (".round($size / 1024).' KB)'.($removed > 0 ? ", {$removed} old backup(s) removed." : '.');
            $this->settings->set('backup_last_success_at', $now->toIso8601String());
            $this->settings->set('backup_attempts', '0');
            $this->record('ok', $message, $name, $actor, $scheduled);

            return ['ok' => true, 'message' => $message, 'file' => $name];
        } catch (Throwable $e) {
            $message = $e instanceof ScoutException ? $e->getMessage() : 'The backup failed: '.$e->getMessage();
            Log::warning('Database backup failed', ['error' => $e->getMessage()]);
            $this->record('failed', $message, null, $actor, $scheduled);

            if ($scheduled && $attempts === 1) {
                $this->notifyAdmins($message);
            }

            return ['ok' => false, 'message' => $message, 'file' => null];
        } finally {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @return array{at: ?Carbon, ok: ?bool, message: ?string, file: ?string}
     */
    public function lastResult(): array
    {
        $status = $this->settings->get('backup_last_status');

        return [
            'at' => $this->time('backup_last_attempt_at'),
            'ok' => $status === null ? null : $status === 'ok',
            'message' => $this->settings->get('backup_last_message'),
            'file' => $this->settings->get('backup_last_file'),
        ];
    }

    /**
     * Write the whole database as SQL (structure and rows) into a gzip file and return its path.
     */
    public function createDump(): string
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new ScoutException("Backups are not available for the {$driver} database.");
        }

        $directory = storage_path('app/private/backups');
        is_dir($directory) || mkdir($directory, 0775, true);
        $path = $directory.'/backup-'.bin2hex(random_bytes(6)).'.sql.gz';
        $out = gzopen($path, 'wb6');

        if ($out === false) {
            throw new ScoutException('The backup file could not be created on the server.');
        }

        $pdo = $connection->getPdo();
        $mysql = $driver !== 'sqlite';

        try {
            gzwrite($out, '-- '.config('scout.name')." database backup\n-- Created ".scout_now()->format('Y-m-d H:i')." (Maldives time)\n\n");
            gzwrite($out, $mysql ? "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n" : "PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n");

            foreach (collect(Schema::getTables())->pluck('name')->sort()->values() as $table) {
                if (in_array($table, self::SKIP_TABLES, true) || str_starts_with($table, 'sqlite_')) {
                    continue;
                }

                $quoted = '`'.str_replace('`', '``', $table).'`';
                $create = $mysql
                    ? (array) $connection->selectOne("SHOW CREATE TABLE {$quoted}")
                    : (array) $connection->selectOne("SELECT sql AS `Create Table` FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

                gzwrite($out, "DROP TABLE IF EXISTS {$quoted};\n".rtrim((string) ($create['Create Table'] ?? array_values($create)[1] ?? ''), ';').";\n\n");

                $batch = [];
                $columns = null;

                foreach ($connection->table($table)->cursor() as $row) {
                    $values = (array) $row;
                    $columns ??= implode(', ', array_map(fn ($c) => '`'.str_replace('`', '``', $c).'`', array_keys($values)));
                    $batch[] = '('.implode(', ', array_map(fn ($v) => $this->literal($pdo, $v), array_values($values))).')';

                    if (count($batch) >= 100) {
                        gzwrite($out, "INSERT INTO {$quoted} ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    gzwrite($out, "INSERT INTO {$quoted} ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
                }

                gzwrite($out, "\n");
            }

            gzwrite($out, $mysql ? "SET FOREIGN_KEY_CHECKS=1;\n" : "COMMIT;\nPRAGMA foreign_keys=ON;\n");
        } finally {
            gzclose($out);
        }

        return $path;
    }

    private function literal(\PDO $pdo, mixed $value): string
    {
        return match (true) {
            $value === null => 'NULL',
            is_bool($value) => $value ? '1' : '0',
            is_int($value) => (string) $value,
            is_float($value) => rtrim(rtrim(sprintf('%.14F', $value), '0'), '.') ?: '0',
            default => (string) $pdo->quote((string) $value),
        };
    }

    private function folderId(): string
    {
        if (! $this->drive->configured()) {
            throw new ScoutException('Google Drive is not connected. Connect a Google account (or upload the service account key) in Settings first.');
        }

        $cached = $this->settings->get('google_drive_backup_folder_id');
        $main = $this->settings->driveFolderId();

        if ($cached && $this->drive->folderAccessible($cached)) {
            return $cached;
        }

        if (! $main) {
            throw new ScoutException('Set the Google Drive folder (Photos folder) in Settings first: the backups are kept in a "'.self::FOLDER_NAME.'" folder inside it.');
        }

        $id = $this->drive->findOrCreateFolder(self::FOLDER_NAME, $main);

        if ($id === null) {
            throw new ScoutException('The "'.self::FOLDER_NAME.'" folder could not be created in Google Drive. '.$this->drive->lastError());
        }

        $this->settings->set('google_drive_backup_folder_id', $id);

        return $id;
    }

    private function prune(string $folderId): int
    {
        $keep = $this->schedule()['keep'];

        if ($keep < 1) {
            return 0;
        }

        $files = $this->drive->listFiles($folderId, 'scout-backup-');
        $removed = 0;

        foreach (array_slice($files, $keep) as $file) {
            $removed += $this->drive->delete((string) $file['id']) ? 1 : 0;
        }

        return $removed;
    }

    private function record(string $status, string $message, ?string $file, ?User $actor, bool $scheduled): void
    {
        $this->settings->set('backup_last_status', $status);
        $this->settings->set('backup_last_message', $message);
        $this->settings->set('backup_last_file', $file);
        $this->audit->record('backup.'.($status === 'ok' ? 'completed' : 'failed'), null, ['scheduled' => $scheduled, 'message' => $message, 'file' => $file], $actor);
    }

    private function notifyAdmins(string $message): void
    {
        $admins = User::query()->active()->whereHas('roleRows', fn ($q) => $q->where('role', Role::Admin->value))->get();
        $this->notifications->sendMany($admins, 'Database backup failed', $message, route('settings.index'));
    }

    private function time(string $key): ?Carbon
    {
        $value = $this->settings->get($key);

        return $value ? Carbon::parse($value)->setTimezone(config('scout.timezone')) : null;
    }
}
