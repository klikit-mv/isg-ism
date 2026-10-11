<?php

namespace Tests\Feature\Backup;

use App\Models\Student;
use App\Services\DatabaseBackupService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function schedule(array $overrides = []): void
    {
        app(DatabaseBackupService::class)->saveSchedule($overrides + ['enabled' => true, 'frequency' => 'daily', 'time' => '02:00', 'weekday' => 5, 'monthday' => 1, 'keep' => 14], $this->admin());
    }

    private function connectDrive(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Cache::put('google.access_token.'.md5('bot@example.iam.gserviceaccount.com'), 'token', 600);
        app(SettingsService::class)->set('google_drive_folder_id', 'mainroot');
    }

    public function test_the_dump_holds_the_structure_and_rows_and_leaves_out_sessions(): void
    {
        Student::factory()->create(['name' => "Aisha O'Neil"]);
        $path = app(DatabaseBackupService::class)->createDump();
        $sql = gzdecode((string) file_get_contents($path));
        @unlink($path);

        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `students`', $sql);
        $this->assertStringContainsString("Aisha O''Neil", $sql);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `users`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
    }

    public function test_a_daily_backup_is_due_after_its_time_in_maldives_time_once(): void
    {
        $backups = app(DatabaseBackupService::class);
        // 20:00 UTC = 01:00 in the Maldives the next day.
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:00:00', 'UTC'));
        $this->schedule(['time' => '02:00']);

        $this->assertFalse($backups->isDue(), 'switching it on does not back up at once');
        Carbon::setTestNow(Carbon::parse('2026-10-10 20:59:00', 'UTC'));
        $this->assertFalse($backups->isDue(), '01:59 Maldives time is before 02:00');
        Carbon::setTestNow(Carbon::parse('2026-10-10 21:01:00', 'UTC'));
        $this->assertTrue($backups->isDue(), '02:01 Maldives time is after 02:00');

        app(SettingsService::class)->set('backup_last_success_at', now()->toIso8601String());
        $this->assertFalse($backups->isDue());
        Carbon::setTestNow(Carbon::parse('2026-10-11 21:01:00', 'UTC'));
        $this->assertTrue($backups->isDue(), 'due again the next day');
    }

    public function test_weekly_and_monthly_slots(): void
    {
        $backups = app(DatabaseBackupService::class);
        Carbon::setTestNow(Carbon::parse('2026-10-11 08:00:00', 'UTC')); // Sunday 13:00 Maldives time
        $this->schedule(['frequency' => 'weekly', 'weekday' => 5, 'time' => '09:30']);

        $this->assertSame('Friday 2026-10-09 09:30', $backups->latestSlot(now())->format('l Y-m-d H:i'));
        $this->assertSame('Friday 2026-10-16 09:30', $backups->nextSlot(now())->format('l Y-m-d H:i'));

        $this->schedule(['frequency' => 'monthly', 'monthday' => 31, 'time' => '01:00']);
        $this->assertSame('2026-09-30 01:00', $backups->latestSlot(now())->format('Y-m-d H:i'));
        $this->assertSame('2026-10-31 01:00', $backups->nextSlot(now())->format('Y-m-d H:i'));
    }

    public function test_a_failed_backup_is_retried_a_few_times_then_waits_for_the_next_slot(): void
    {
        $backups = app(DatabaseBackupService::class);
        $settings = app(SettingsService::class);
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'UTC'));
        $this->schedule(['time' => '02:00']);
        $settings->set('backup_enabled_at', Carbon::parse('2026-10-09 10:00:00', 'UTC')->toIso8601String());

        $this->assertTrue($backups->isDue());
        $backups->run(null, true); // Drive is not connected: fails
        $this->assertFalse($backups->isDue(), 'not again straight away');

        foreach ([31, 62] as $minutes) {
            Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'UTC')->addMinutes($minutes));
            $this->assertTrue($backups->isDue());
            $backups->run(null, true);
        }

        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00', 'UTC')->addMinutes(120));
        $this->assertFalse($backups->isDue(), 'three attempts for this slot are used up');
        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00:00', 'UTC'));
        $this->assertTrue($backups->isDue(), 'the next day tries again');
    }

    public function test_a_backup_is_uploaded_to_the_drive_backup_folder_and_old_ones_are_removed(): void
    {
        $this->connectDrive();
        $this->schedule(['keep' => 2]);
        Http::fake([
            'www.googleapis.com/upload/*' => Http::response(['id' => 'newBackup']),
            'www.googleapis.com/drive/v3/files*' => function ($request) {
                if ($request->method() === 'DELETE') {
                    return Http::response('', 204);
                }
                if ($request->method() === 'POST') {
                    return Http::response(['id' => 'backupFolder']);
                }

                return str_contains($request->url(), 'mimeType')
                    ? Http::response(['files' => []])
                    : Http::response(['files' => [['id' => 'f1', 'name' => 'scout-backup-3', 'createdTime' => '3'], ['id' => 'f2', 'name' => 'scout-backup-2', 'createdTime' => '2'], ['id' => 'f3', 'name' => 'scout-backup-1', 'createdTime' => '1']]]);
            },
        ]);

        $result = app(DatabaseBackupService::class)->run($this->admin());

        $this->assertTrue($result['ok'], $result['message']);
        $this->assertSame('backupFolder', app(SettingsService::class)->get('google_drive_backup_folder_id'));
        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->body(), '"name":"Database backups"') && str_contains($r->body(), '"parents":["mainroot"]'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'upload/drive') && str_contains($r->body(), 'scout-backup-') && str_contains($r->body(), '"parents":["backupFolder"]'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'f3'));
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), 'f1'));
        $this->assertSame([], glob(storage_path('app/private/backups/*.sql.gz')) ?: []);
        $this->assertTrue(app(DatabaseBackupService::class)->lastResult()['ok']);
    }

    public function test_without_google_the_backup_fails_clearly(): void
    {
        $result = app(DatabaseBackupService::class)->run($this->admin());

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Google Drive is not connected', $result['message']);
        $this->assertFalse(app(DatabaseBackupService::class)->lastResult()['ok']);
    }

    public function test_the_schedule_is_set_in_settings_by_admins_only(): void
    {
        $this->actingAs($this->admin())->get('/settings')->assertOk()->assertSee('Database backup to Google Drive')->assertSee('Back up now');

        $this->actingAs($this->admin())->post('/settings/backup', ['enabled' => '1', 'frequency' => 'weekly', 'time' => '03:15', 'weekday' => 0, 'monthday' => 1, 'keep' => 7])->assertSessionHas('success');
        $schedule = app(DatabaseBackupService::class)->schedule();
        $this->assertSame(['enabled' => true, 'frequency' => 'weekly', 'time' => '03:15', 'weekday' => 0, 'monthday' => 1, 'keep' => 7], $schedule);

        $this->actingAs($this->admin())->post('/settings/backup', ['frequency' => 'hourly', 'time' => '25:00', 'weekday' => 9, 'monthday' => 40, 'keep' => -1])->assertSessionHasErrors(['frequency', 'time', 'weekday', 'monthday', 'keep']);
        $this->actingAs($this->leader())->post('/settings/backup', ['frequency' => 'daily', 'time' => '02:00', 'weekday' => 1, 'monthday' => 1, 'keep' => 7])->assertForbidden();
        $this->actingAs($this->leader())->post('/settings/backup/run')->assertForbidden();
    }

    public function test_the_scheduled_command_only_works_when_a_backup_is_due(): void
    {
        Http::fake();
        $this->schedule();

        Artisan::call('scout:backup', ['--scheduled' => true]);

        Http::assertNothingSent();
        $this->assertNull(app(DatabaseBackupService::class)->lastResult()['at']);
    }
}
