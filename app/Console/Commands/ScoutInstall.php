<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class ScoutInstall extends Command
{
    protected $signature = 'scout:install {--no-demo : Skip the demo data in local/testing}';

    protected $description = 'Migrate, link storage and seed demo data (local/testing only)';

    public function handle(): int
    {
        $this->call('migrate', ['--force' => true]);

        if (! is_link(public_path('storage'))) {
            $this->call('storage:link');
        }

        if (app()->environment(['local', 'testing']) && ! $this->option('no-demo')) {
            $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        } else {
            $this->info('Demo data is only seeded in local or testing environments. Create the first admin with `php artisan scout:create-admin`.');
        }

        return self::SUCCESS;
    }
}
