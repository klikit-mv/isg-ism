<?php

namespace App\Livewire\Attendance;

use App\Enums\RoverAttendanceStatus;
use App\Exceptions\ScoutException;
use App\Models\Activity;
use App\Models\RoverAttendanceRecord;
use App\Services\RoverAttendanceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The Rover register: required Rovers take Present/Absent/Excused; optional,
 * additional and newly added Rovers can only be Present.
 */
class RoverMark extends Component
{
    #[Locked]
    public int $activityId;

    /** @var array<int, string> student id => status or '' */
    public array $marks = [];

    /** @var list<int> */
    public array $added = [];

    public string $addSearch = '';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(Activity $activity): void
    {
        abort_unless(Auth::user()->can('markAttendance', $activity), 403);

        $this->activityId = $activity->id;
        $this->loadMarks();
    }

    private function activity(): Activity
    {
        return Activity::query()->findOrFail($this->activityId);
    }

    private function loadMarks(): void
    {
        $this->marks = RoverAttendanceRecord::query()
            ->where('activity_id', $this->activityId)
            ->pluck('status', 'student_id')
            ->map(fn ($status) => $status instanceof RoverAttendanceStatus ? $status->value : (string) $status)
            ->all();
        $this->added = [];
    }

    public function addRover(int $studentId): void
    {
        if (! in_array($studentId, $this->added, true)) {
            $this->added[] = $studentId;
        }

        $this->marks[$studentId] = RoverAttendanceStatus::Present->value;
        $this->addSearch = '';
    }

    public function save(RoverAttendanceService $service): void
    {
        $this->flash = null;
        $this->error = null;

        try {
            $result = $service->mark($this->activity(), Auth::user(), array_filter($this->marks, fn ($v) => $v !== '' && $v !== null && $v !== false));
        } catch (ScoutException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->flash = "Saved {$result['marked']} Rover mark(s).";
        $this->loadMarks();
    }

    public function render(RoverAttendanceService $service): View
    {
        $participants = $service->participants($this->activity());
        $available = $participants['available'];
        $addedRovers = $available->whereIn('id', $this->added)->values();
        $remaining = $available->reject(fn ($s) => in_array($s->id, $this->added, true));

        if ($this->addSearch !== '') {
            $term = mb_strtolower($this->addSearch);
            $remaining = $remaining->filter(fn ($s) => str_contains(mb_strtolower($s->name.' '.$s->index_number), $term));
        }

        return view('livewire.attendance.rover-mark', [
            'required' => $participants['required'],
            'optional' => $participants['optional'],
            'additional' => $participants['additional']->concat($addedRovers),
            'available' => $remaining->take(20),
        ]);
    }
}
