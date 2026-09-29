<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scout:install {--no-demo : Skip the demo data in local/testing}')]
#[Description('Migrate, link storage and seed demo data (local/testing only)')]
class ScoutInstall extends Command
{
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
