<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('scout:create-admin {national_id : The admin\'s National ID} {name : Full name} {--email= : Email address}')]
#[Description('Create (or promote) the first administrator and print a one-time PIN')]
class ScoutCreateAdmin extends Command
{
    public function handle(AuditLogService $audit): int
    {
        $nationalId = Str::upper(trim((string) $this->argument('national_id')));
        $pin = (string) random_int(100000, 999999);

        $user = User::withTrashed()->firstOrNew(['national_id' => $nationalId]);
        $user->fill(['name' => $this->argument('name'), 'email' => $this->option('email') ?: $user->email]);
        $user->forceFill(['password' => $pin, 'status' => UserStatus::Active, 'verified_at' => now(), 'legacy_pin_hash' => null, 'legacy_pin_salt' => null])->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $user->assignRole(Role::Admin);
        $audit->record('user.admin_created', $user, ['national_id' => $nationalId]);

        $this->info("Admin {$user->name} ({$nationalId}) is ready.");
        $this->line("One-time PIN: {$pin}  — sign in and change it from Profile right away.");

        return self::SUCCESS;
    }
}
