<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo data is only ever seeded in local or testing environments.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Skipping demo data outside local/testing. Use `php artisan scout:create-admin` for the first admin.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
