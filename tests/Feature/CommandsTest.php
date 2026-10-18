<?php

namespace Tests\Feature;

use App\Models\ClassFee;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Import\LegacyImportTest;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_demo_data_passes_the_integrity_check(): void
    {
        Storage::fake('certificates');
        $this->seed(DemoSeeder::class);

        $this->artisan('scout:verify-integrity')->assertSuccessful();
        $this->artisan('scout:recalculate-balances')->assertSuccessful();
    }

    public function test_integrity_check_fails_on_problems(): void
    {
        Storage::fake('certificates');
        $this->seed(DemoSeeder::class);
        DB::table('user_roles')->insert(['user_id' => User::query()->value('id'), 'role' => 'superhero']);

        $this->artisan('scout:verify-integrity')->assertFailed();
    }

    public function test_recalculate_balances_detects_and_repairs(): void
    {
        Storage::fake('certificates');
        $this->seed(DemoSeeder::class);
        $fee = ClassFee::query()->where('paid_amount', '>', 0)->firstOrFail();
        $fee->forceFill(['paid_amount' => '999.00'])->save();

        $this->artisan('scout:recalculate-balances')->assertFailed();
        $this->artisan('scout:recalculate-balances --repair')->assertSuccessful();

        $this->assertNotSame('999.00', $fee->fresh()->paid_amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'balance.repaired']);
    }

    public function test_ledger_export_writes_a_file(): void
    {
        Storage::fake('certificates');
        $this->seed(DemoSeeder::class);
        $path = sys_get_temp_dir().'/students-'.uniqid().'.csv';

        $this->artisan('scout:export', ['type' => 'students', '--format' => 'csv', '--path' => $path])->assertSuccessful();

        $this->assertStringContainsString('national_id', file_get_contents($path));
        $this->artisan('scout:export', ['type' => 'secrets'])->assertFailed();
    }

    public function test_legacy_import_command_is_a_dry_run_unless_forced(): void
    {
        $path = LegacyImportTest::demoWorkbook();

        $this->artisan('scout:import-legacy', ['--file' => $path])->expectsOutputToContain('Dry run only')->assertSuccessful();
        $this->assertDatabaseCount('students', 0);

        $this->artisan('scout:import-legacy', ['--file' => $path, '--force' => true])->expectsOutputToContain('Import saved')->assertSuccessful();
        $this->assertDatabaseCount('students', 3);
    }

    public function test_create_admin_prints_a_one_time_pin(): void
    {
        $this->artisan('scout:create-admin', ['national_id' => 'a5550001', 'name' => 'First Admin'])
            ->expectsOutputToContain('One-time PIN')
            ->assertSuccessful();

        $this->assertTrue(User::query()->where('national_id', 'A5550001')->firstOrFail()->isAdmin());
    }

    public function test_cleanup_runs(): void
    {
        $this->artisan('scout:cleanup')->assertSuccessful();
    }
}
