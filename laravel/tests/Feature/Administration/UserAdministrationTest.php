<?php

namespace Tests\Feature\Administration;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_and_searches_users(): void
    {
        $admin = $this->admin();
        User::factory()->create(['name' => 'Aishath Leader']);
        User::factory()->create(['name' => 'Moosa Parent']);

        $this->actingAs($admin)->get('/users?q=Aishath')
            ->assertOk()
            ->assertSee('Aishath Leader')
            ->assertDontSee('Moosa Parent');
    }

    public function test_admin_creates_user_with_roles_and_permissions(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'New Leader',
            'national_id' => 'a998877',
            'email' => 'leader@example.com',
            'pin' => '2468',
            'roles' => ['leader', 'parent'],
            'permissions' => ['canVerifyPayments'],
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('national_id', 'A998877')->firstOrFail();
        $this->assertTrue($user->hasRole(Role::Leader));
        $this->assertTrue($user->hasRole(Role::Parent));
        $this->assertTrue($user->hasPermission(Permission::VerifyPayments));
        $this->assertFalse($user->hasPermission(Permission::ManageShop));
        $this->assertTrue(Hash::check('2468', $user->password));
    }

    public function test_pin_reset_kills_sessions_and_never_logs_the_pin(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->post("/users/{$user->uuid}/pin", ['pin' => '9753'])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('9753', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $log = AuditLog::query()->where('action', 'user.pin_reset')->firstOrFail();
        $this->assertStringNotContainsString('9753', json_encode($log->details));
    }

    public function test_edit_updates_roles_and_status_but_not_pin(): void
    {
        $admin = $this->admin();
        $user = User::factory()->leader()->create();

        $this->actingAs($admin)->put("/users/{$user->uuid}", [
            'name' => 'Renamed',
            'email' => $user->email,
            'status' => 'inactive',
            'roles' => ['parent'],
            'pin' => '0000',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Renamed', $user->name);
        $this->assertFalse($user->isActive());
        $this->assertFalse($user->hasRole(Role::Leader));
        $this->assertTrue(Hash::check('1234', $user->password));
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/users/{$admin->uuid}")->assertSessionHas('error');

        $this->assertNotSoftDeleted($admin);
    }

    public function test_admin_soft_deletes_another_user(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();

        $this->actingAs($admin)->delete("/users/{$user->uuid}");

        $this->assertSoftDeleted($user);
    }

    public function test_non_admins_cannot_open_user_administration(): void
    {
        $leader = $this->leader(Permission::VerifyPayments);

        $this->actingAs($leader)->get('/users')->assertForbidden();
        $this->actingAs($leader)->post('/users', [])->assertForbidden();
    }

    public function test_inactive_admin_gets_no_admin_powers(): void
    {
        $admin = User::factory()->admin()->inactive()->create();

        $this->actingAs($admin)->get('/users')->assertForbidden();
    }
}
