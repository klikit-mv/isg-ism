<?php

namespace Tests\Feature\Access;

use App\Enums\ParentLinkStatus;
use App\Enums\Role;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_sees_only_approved_children(): void
    {
        $mine = Student::factory()->create(['name' => 'Approved Child']);
        $other = Student::factory()->create(['name' => 'Someone Else']);
        $parent = $this->parentOf($mine);

        $this->actingAs($parent)->get('/family')->assertOk()->assertSee('Approved Child')->assertDontSee('Someone Else');
        $this->actingAs($parent)->get("/family/students/{$mine->uuid}")->assertOk();
        $this->actingAs($parent)->get("/family/students/{$other->uuid}")->assertForbidden();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonGrantingStatuses(): array
    {
        return [
            'pending' => [ParentLinkStatus::Pending->value],
            'rejected' => [ParentLinkStatus::Rejected->value],
            'inactive' => [ParentLinkStatus::Inactive->value],
        ];
    }

    #[DataProvider('nonGrantingStatuses')]
    public function test_non_approved_links_grant_nothing(string $status): void
    {
        $child = Student::factory()->create();
        $parent = User::factory()->withRoles(Role::Parent)->create();
        $parent->parentLinks()->create(['student_id' => $child->id, 'status' => $status]);

        $this->actingAs($parent)->get("/family/students/{$child->uuid}")->assertForbidden();
    }

    public function test_student_sees_only_own_record(): void
    {
        $user = $this->studentUser();
        $other = Student::factory()->create();

        $this->actingAs($user)->get('/me')->assertOk()->assertSee($user->student->name);
        $this->actingAs($user)->get("/students/{$other->uuid}")->assertForbidden();
        $this->actingAs($user)->get("/family/students/{$other->uuid}")->assertForbidden();
    }

    public function test_leader_scope_is_bound_to_led_groups(): void
    {
        $leader = $this->leader();
        $inScope = Student::factory()->create(['name' => 'In Scope']);
        $outOfScope = Student::factory()->create(['name' => 'Out Of Scope']);
        Group::factory()->ledBy($leader)->withMembers($inScope)->create();

        $this->actingAs($leader)->get('/students')->assertOk()->assertSee('In Scope')->assertDontSee('Out Of Scope');
        $this->actingAs($leader)->get("/students/{$inScope->uuid}")->assertOk();
        $this->actingAs($leader)->get("/students/{$outOfScope->uuid}")->assertForbidden();
    }

    public function test_leader_sees_every_pending_registration(): void
    {
        $leader = $this->leader();
        $pending = Student::factory()->pending()->create(['name' => 'Waiting Scout']);

        $this->actingAs($leader)->get('/students')->assertSee('Waiting Scout');
        $this->actingAs($leader)->get("/students/{$pending->uuid}")->assertOk();
    }

    public function test_admin_is_unrestricted(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin())->get("/students/{$student->uuid}")->assertOk();
    }

    public function test_multi_role_user_keeps_independent_abilities(): void
    {
        $child = Student::factory()->create(['name' => 'Own Child']);
        $groupScout = Student::factory()->create(['name' => 'Group Scout']);
        $user = User::factory()->withRoles(Role::Leader, Role::Parent)->create();
        $user->parentLinks()->create(['student_id' => $child->id, 'status' => 'approved']);
        Group::factory()->ledBy($user)->withMembers($groupScout)->create();

        $this->actingAs($user)->get('/family')->assertOk()->assertSee('Own Child')->assertDontSee('Group Scout');
        $this->actingAs($user)->get('/students')->assertOk()->assertSee('Group Scout');
    }

    public function test_profile_update_cannot_escalate_roles(): void
    {
        $user = $this->studentUser();

        $this->actingAs($user)->patch('/profile', [
            'name' => 'Still A Scout',
            'email' => 'scout@example.com',
            'roles' => ['admin'],
            'status' => 'active',
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Still A Scout', $user->name);
        $this->assertFalse($user->hasRole(Role::Admin));
        $this->actingAs($user)->get('/users')->assertForbidden();
    }
}
