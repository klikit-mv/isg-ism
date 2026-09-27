<?php

namespace Tests\Feature\Operations;

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
}
