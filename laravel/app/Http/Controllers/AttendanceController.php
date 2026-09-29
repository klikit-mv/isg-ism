<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Services\LeaderScopeService;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private LeaderScopeService $scope) {}

    /**
     * Pick an existing activity to mark.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->isStaff(), 403);

        return view('attendance.index', [
            'activities' => $this->activities($request),
            'title' => 'Mark attendance',
            'markRoute' => 'attendance.mark',
        ]);
    }

    public function mark(Activity $activity): View
    {
        $this->authorize('markAttendance', $activity);

        return view('attendance.mark', ['activity' => $activity]);
    }

    public function roverIndex(Request $request): View
    {
        abort_unless($request->user()->isStaff(), 403);

        return view('attendance.index', [
            'activities' => $this->activities($request),
            'title' => 'Rover attendance',
            'markRoute' => 'rover-attendance.mark',
        ]);
    }

    public function roverMark(Activity $activity): View
    {
        $this->authorize('markAttendance', $activity);

        return view('attendance.rover-mark', ['activity' => $activity]);
    }

    private function activities(Request $request)
    {
        $query = Activity::query()
            ->withCount('attendanceRecords')
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"));

        return $this->scope->constrainActivities($query, $request->user())
            ->orderByDesc('date')
            ->paginate(Pagination::MAX)
            ->withQueryString();
    }
}
