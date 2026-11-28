<?php

namespace Tests\Feature\Attendance;

use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Models\Activity;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ScoutAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_activity_notifies_roster_scouts_and_parents_once(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $scout = Student::factory()->create();
        $scoutUser = $this->studentUser($scout);
        $parent = $this->parentOf($scout);
        $group = Group::factory()->withMembers($scout)->create();

        $this->actingAs($admin)->post('/activities', [
            'name' => 'Camp', 'date' => '2026-10-01', 'groups' => [$group->id], 'charge_fee' => '1', 'fee_amount' => '',
        ])->assertSessionHasNoErrors();

        $activity = Activity::query()->firstOrFail();
        $this->assertTrue($activity->charge_fee);
        $this->assertSame('50.00', $activity->fee_amount);
        Notification::assertSentTo($scoutUser, ScoutAlert::class, fn ($n) => str_contains($n->body, '01.10.2026'));
        Notification::assertSentToTimes($parent, ScoutAlert::class, 1);
        Notification::assertNotSentTo($admin, ScoutAlert::class);
    }

    public function test_parent_who_is_also_on_roster_is_not_notified_twice(): void
    {
        Notification::fake();
        $child = Student::factory()->create();
        $parentScout = User::factory()->forStudent(Student::factory()->create())->create();
        $parentScout->assignRole(Role::Parent);
        $parentScout->parentLinks()->create(['student_id' => $child->id, 'status' => 'approved']);

        $this->actingAs($this->admin())->post('/activities', ['name' => 'Hike', 'date' => '2026-10-01', 'all_students' => '1']);

        Notification::assertSentToTimes($parentScout, ScoutAlert::class, 1);
    }

    public function test_activity_needs_a_target(): void
    {
        $this->actingAs($this->admin())->post('/activities', ['name' => 'Nobody', 'date' => '2026-10-01'])
            ->assertSessionHasErrors('sections');
    }

    public function test_leader_sees_only_activities_in_scope(): void
    {
        $leader = $this->leader();
        $cub = Student::factory()->section(ScoutSection::CubScout)->create();
        $group = Group::factory()->ledBy($leader)->withMembers($cub)->create();
        Activity::factory()->forGroups($group)->create(['name' => 'Group Meeting']);
        Activity::factory()->forSections([ScoutSection::CubScout])->create(['name' => 'Cub Day']);
        Activity::factory()->forAll()->create(['name' => 'Everyone Day']);
        Activity::factory()->forSections([ScoutSection::Rover])->create(['name' => 'Rover Night']);

        $this->actingAs($leader)->get('/activities')
            ->assertSee('Group Meeting')->assertSee('Cub Day')->assertSee('Everyone Day')->assertDontSee('Rover Night');
    }

    public function test_only_admin_deletes_activities(): void
    {
        $leader = $this->leader();
        $group = Group::factory()->ledBy($leader)->withMembers(Student::factory()->create())->create();
        $activity = Activity::factory()->forGroups($group)->create();

        $this->actingAs($leader)->delete("/activities/{$activity->uuid}")->assertForbidden();
        $this->actingAs($this->admin())->delete("/activities/{$activity->uuid}");
        $this->assertSoftDeleted($activity);
    }

    public function test_leader_edits_activity_in_scope(): void
    {
        $leader = $this->leader();
        $group = Group::factory()->ledBy($leader)->withMembers(Student::factory()->create())->create();
        $activity = Activity::factory()->forGroups($group)->create();

        $this->actingAs($leader)->put("/activities/{$activity->uuid}", [
            'name' => 'Renamed', 'date' => '2026-11-01', 'groups' => [$group->id], 'sections' => ['Scout'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $activity->fresh()->name);
        $this->assertSame([ScoutSection::Scout], $activity->fresh()->sections());
    }
}
