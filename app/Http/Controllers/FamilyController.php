<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AttendanceHistory;
use App\Support\StudentRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A parent's view of their approved children only.
 */
class FamilyController extends Controller
{
    public function index(Request $request): View
    {
        $children = Student::query()->whereIn('id', $request->user()->approvedChildIds() ?: [0])->orderBy('name')->get();

        return view('family.index', ['children' => $children]);
    }

    public function show(Request $request, Student $student, string $tab = 'profile'): View
    {
        $this->authorizeChild($request, $student);

        return view('family.show', StudentRecord::data($request->user(), $student, $tab, 'family'));
    }

    public function certificates(Request $request, Student $student): View
    {
        return $this->show($request, $student, 'certificates');
    }

    public function badgeRequests(Request $request, Student $student): View
    {
        return $this->show($request, $student, 'badge-requests');
    }

    public function leadership(Request $request, Student $student): View
    {
        return $this->show($request, $student, 'leadership');
    }

    public function attendance(Request $request): View
    {
        $childIds = $request->user()->approvedChildIds();

        return view('attendance.history', [
            'title' => 'Family attendance',
            'records' => AttendanceHistory::paginate($request, $childIds),
            'children' => Student::query()->whereIn('id', $childIds ?: [0])->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        abort_unless(in_array($student->id, $request->user()->approvedChildIds(), true), 403);
    }
}
