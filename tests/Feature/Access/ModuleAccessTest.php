<?php

namespace Tests\Feature\Access;

use App\Enums\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_shows_only_modules_the_user_can_open(): void
    {
        $parent = $this->parentOf(Student::factory()->create());

        $this->actingAs($parent)->get('/dashboard')
            ->assertOk()
            ->assertSee('data-module="family"', false)
            ->assertDontSee('data-module="operations"', false)
            ->assertDontSee('data-module="administration"', false);
    }

    public function test_admin_sees_administration_module(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')
            ->assertSee('data-module="administration"', false)
            ->assertSee('data-module="operations"', false);
    }

    public function test_routes_of_forbidden_modules_return_403(): void
    {
        $parent = $this->parentOf();

        $this->actingAs($parent)->get('/students')->assertForbidden();
        $this->actingAs($parent)->get('/groups')->assertForbidden();
        $this->actingAs($parent)->get('/me')->assertForbidden();
    }

    public function test_parent_who_is_also_a_scout_gets_both_modules_separately(): void
    {
        $user = User::factory()->forStudent()->withRoles(Role::Parent)->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertSee('data-module="family"', false)
            ->assertSee('data-module="self"', false);

        $this->actingAs($user)->get('/modules/family')->assertRedirect(route('family.index'));
        $this->assertSame('family', session('current_module'));
        $this->actingAs($user)->get('/me')->assertOk();
        $this->assertSame('self', session('current_module'));
    }

    public function test_entering_a_forbidden_module_is_refused(): void
    {
        $this->actingAs($this->studentUser())->get('/modules/administration')->assertForbidden();
    }

    public function test_guests_are_sent_to_sign_in(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/events')->assertRedirect(route('login'));
    }

    public function test_home_page_is_public_and_signed_in_users_go_to_the_dashboard(): void
    {
        $this->get('/')->assertOk()->assertSee('Upcoming events')->assertSee('Sign in');
        $this->actingAs($this->admin())->get('/')->assertRedirect(route('dashboard'));
    }
}
