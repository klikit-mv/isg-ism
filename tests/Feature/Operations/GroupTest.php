<?php

namespace Tests\Feature\Operations;

use App\Enums\ScoutSection;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_group_and_becomes_first_leader(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/groups', ['name' => 'Eagle Patrol', 'type' => 'Patrol']);

        $group = Group::query()->where('name', 'Eagle Patrol')->firstOrFail();
        $this->assertSame($admin->id, $group->owner_id);
        $this->assertTrue($group->leaders->contains($admin));
    }

    public function test_leader_cannot_create_or_delete_groups(): void
    {
        $leader = $this->leader();
        $group = Group::factory()->ledBy($leader)->create();

        $this->actingAs($leader)->post('/groups', ['name' => 'Nope'])->assertForbidden();
        $this->actingAs($leader)->delete("/groups/{$group->uuid}")->assertForbidden();
    }

    public function test_leader_edits_membership_of_own_group_only(): void
    {
        $leader = $this->leader();
        $own = Group::factory()->ledBy($leader)->create();
        $other = Group::factory()->create();
        $scout = Student::factory()->create();

        $this->actingAs($leader)->put("/groups/{$own->uuid}/membership", ['members' => [$scout->id], 'leaders' => [$leader->id]])
            ->assertSessionHasNoErrors();
        $this->assertTrue($own->members()->whereKey($scout->id)->exists());

        $this->actingAs($leader)->put("/groups/{$other->uuid}/membership", ['members' => [$scout->id]])->assertForbidden();
    }

    public function test_only_leader_role_users_can_lead(): void
    {
        $admin = $this->admin();
        $group = Group::factory()->create();
        $parent = User::factory()->parentRole()->create();

        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['leaders' => [$parent->id]])
            ->assertSessionHas('error', 'Only users with the leader role can be assigned as group leaders.');
    }

    public function test_assistant_leaders_must_be_rovers(): void
    {
        $admin = $this->admin();
        $group = Group::factory()->create();
        $scout = Student::factory()->create();
        $rover = Student::factory()->rover()->create();

        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['assistant_leaders' => [$scout->id]])
            ->assertSessionHas('error', 'Assistant leaders must be Rover scouts.');

        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['assistant_leaders' => [$rover->id]])
            ->assertSessionHas('success');
        $this->assertTrue($group->assistantLeaders()->whereKey($rover->id)->exists());
    }

    public function test_section_group_only_offers_and_accepts_scouts_of_that_section(): void
    {
        $admin = $this->admin();
        $cub = Student::factory()->section(ScoutSection::CubScout)->create();
        $scout = Student::factory()->section(ScoutSection::Scout)->create();

        $this->actingAs($admin)->post('/groups', ['name' => 'Cub Pack', 'type' => 'Patrol', 'section' => 'Cub Scout'])->assertSessionHasNoErrors();
        $group = Group::query()->where('name', 'Cub Pack')->firstOrFail();
        $this->assertSame(ScoutSection::CubScout, $group->section);

        $this->actingAs($admin)->get("/groups/{$group->uuid}")->assertOk()->assertSee(e($cub->name), false)->assertDontSee(e($scout->name), false);

        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['members' => [$scout->id]])
            ->assertSessionHas('error', "{$scout->name} is not in the Cub Scout section, so cannot join this group.");
        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['members' => [$cub->id]])->assertSessionHas('success');

        $this->actingAs($admin)->put("/groups/{$group->uuid}", ['name' => 'Cub Pack', 'section' => 'Scout', 'status' => 'Active'])
            ->assertSessionHas('error', "Some members are not in the Scout section. Remove them first, then change the group's section.");
    }

    public function test_subgroups_can_be_created_renamed_assigned_and_deleted(): void
    {
        $admin = $this->admin();
        $group = Group::factory()->create();
        $scout = Student::factory()->create();
        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['members' => [$scout->id], 'leaders' => [$admin->id]]);

        $this->actingAs($admin)->post("/groups/{$group->uuid}/subgroups", ['name' => 'Eagle'])->assertSessionHas('success');
        $this->actingAs($admin)->post("/groups/{$group->uuid}/subgroups", ['name' => 'Eagle'])->assertSessionHas('error');
        $eagle = $group->subgroups()->firstOrFail();

        $this->actingAs($admin)->put("/groups/{$group->uuid}/subgroup-assignments", ['subgroup' => [$scout->id => $eagle->id]])->assertSessionHas('success');
        $this->assertSame($eagle->id, $group->members()->first()->pivot->subgroup_id);
        $this->actingAs($admin)->get("/students/{$scout->uuid}")->assertSee("{$group->name} › Eagle");

        $this->actingAs($admin)->put("/groups/{$group->uuid}/membership", ['members' => [$scout->id], 'leaders' => [$admin->id]]);
        $this->assertSame($eagle->id, $group->members()->first()->pivot->subgroup_id, 'saving membership keeps sub-groups');

        $this->actingAs($admin)->put("/groups/{$group->uuid}/subgroups/{$eagle->uuid}", ['name' => 'Falcon'])->assertSessionHas('success');
        $this->assertSame('Falcon', $eagle->fresh()->name);

        $other = Group::factory()->create();
        $this->actingAs($admin)->put("/groups/{$other->uuid}/subgroup-assignments", ['subgroup' => [$scout->id => $eagle->id]]);
        $this->actingAs($admin)->put("/groups/{$other->uuid}/subgroups/{$eagle->uuid}", ['name' => 'X'])->assertNotFound();

        $this->actingAs($admin)->delete("/groups/{$group->uuid}/subgroups/{$eagle->uuid}")->assertSessionHas('success');
        $this->assertNull($group->members()->first()->pivot->subgroup_id);
    }

    public function test_group_status_can_be_changed_and_the_student_form_has_no_patrol_or_class(): void
    {
        $admin = $this->admin();
        $group = Group::factory()->create();

        $this->actingAs($admin)->put("/groups/{$group->uuid}", ['name' => $group->name, 'status' => 'Inactive'])->assertSessionHas('success');
        $this->assertSame('Inactive', $group->fresh()->status->value);

        $this->actingAs($admin)->get('/students/create')->assertOk()->assertDontSee('name="patrol"', false)->assertDontSee('name="class_name"', false);
    }
}
