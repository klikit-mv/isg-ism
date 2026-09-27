<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\AttendanceHistory;
use App\Support\StudentRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A scout's own record.
 */
class SelfController extends Controller
{
    public function show(Request $request, string $tab = 'profile'): View
    {
        $student = $this->ownStudent($request);

        if ($student === null) {
            return view('self.empty');
        }

        return view('self.show', StudentRecord::data($request->user(), $student, $tab, 'self'));
    }

    public function certificates(Request $request): View
    {
        return $this->show($request, 'certificates');
    }

    public function badgeRequests(Request $request): View
    {
        return $this->show($request, 'badge-requests');
    }

    public function leadership(Request $request): View
    {
        return $this->show($request, 'leadership');
    }

    public function attendance(Request $request): View
    {
        $student = $this->ownStudent($request);

        return view('attendance.history', [
            'title' => 'My attendance',
            'records' => AttendanceHistory::paginate($request, $student ? [$student->id] : []),
            'children' => [],
        ]);
    }

    private function ownStudent(Request $request): ?Student
    {
        return $request->user()->student;
    }
}
