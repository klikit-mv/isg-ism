<?php

namespace Tests\Feature\Attendance;

use App\Enums\RoverAttendanceStatus;
use App\Enums\ScoutSection;
use App\Exceptions\ScoutException;
use App\Livewire\Attendance\RoverMark;
use App\Models\Activity;
use App\Models\ClassFee;
use App\Models\Group;
use App\Models\RoverAttendanceRecord;
use App\Models\Student;
use App\Services\RoverAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoverAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_participants_are_split_into_required_optional_and_available(): void
    {
        $requiredRover = Student::factory()->rover()->create();
        $assistant = Student::factory()->rover()->create();
        $other = Student::factory()->rover()->create();
        $group = Group::factory()->withMembers(Student::factory()->create())->create();
        $group->assistantLeaders()->attach($assistant->id);
        $activity = Activity::factory()->forGroups($group)->forSections([ScoutSection::Rover])->create();
        $activity->syncSections([]);
        $group->members()->attach($requiredRover->id);

        $participants = app(RoverAttendanceService::class)->participants($activity);

        $this->assertSame([$requiredRover->id], $participants['required']->pluck('id')->all());
        $this->assertSame([$assistant->id], $participants['optional']->pluck('id')->all());
        $this->assertSame([$other->id], $participants['available']->pluck('id')->all());
    }

    public function test_optional_rover_cannot_be_marked_absent(): void
    {
        $assistant = Student::factory()->rover()->create();
        $group = Group::factory()->create();
        $group->assistantLeaders()->attach($assistant->id);
        $activity = Activity::factory()->forGroups($group)->create();

        $this->expectException(ScoutException::class);
        $this->expectExceptionMessage('Optional Rovers can only be marked Present.');

        app(RoverAttendanceService::class)->mark($activity, $this->admin(), [$assistant->id => 'Absent']);
    }

    public function test_required_rover_takes_absent_but_never_late_and_no_fee_is_created(): void
    {
        $rover = Student::factory()->rover()->create();
        $activity = Activity::factory()->forAll()->charged()->create();

        app(RoverAttendanceService::class)->mark($activity, $this->admin(), [$rover->id => 'Absent']);

        $this->assertTrue(RoverAttendanceRecord::query()->where('student_id', $rover->id)->where('status', 'Absent')->where('is_required', true)->exists());
        $this->assertSame(0, ClassFee::query()->count());
        $this->assertNull(RoverAttendanceStatus::tryFrom('Late'));
    }

    public function test_unknown_students_are_rejected(): void
    {
        $scout = Student::factory()->create();
        $activity = Activity::factory()->forAll()->create();

        $this->expectException(ScoutException::class);

        app(RoverAttendanceService::class)->mark($activity, $this->admin(), [$scout->id => 'Present']);
    }

    public function test_other_rover_is_added_as_present_through_the_register(): void
    {
        $other = Student::factory()->rover()->create();
        $activity = Activity::factory()->forSections([ScoutSection::Scout])->create();

        Livewire::actingAs($this->admin())->test(RoverMark::class, ['activity' => $activity])
            ->call('addRover', $other->id)
            ->call('save')
            ->assertSet('error', null);

        $record = RoverAttendanceRecord::query()->firstOrFail();
        $this->assertSame('Present', $record->status->value);
        $this->assertFalse($record->is_required);
    }
}
