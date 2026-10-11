<?php

namespace App\Support;

use App\Models\AttendanceRecord;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Attendance records for families and scouts, with text, child, status and date filters.
 */
final class AttendanceHistory
{
    /**
     * @param  list<int>  $studentIds
     */
    public static function paginate(Request $request, array $studentIds): LengthAwarePaginator
    {
        return AttendanceRecord::query()
            ->with('activity', 'student')
            ->whereIn('attendance_records.student_id', $studentIds === [] ? [0] : $studentIds)
            ->join('activities', 'activities.id', '=', 'attendance_records.activity_id')
            ->select('attendance_records.*')
            ->when($request->query('q'), fn ($q, $term) => $q->where('activities.name', 'like', "%{$term}%"))
            ->when($request->query('child'), fn ($q, $child) => $q->whereHas('student', fn ($s) => $s->where('uuid', $child)))
            ->when($request->query('status'), fn ($q, $status) => $q->where('attendance_records.status', $status))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('activities.date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('activities.date', '<=', $to))
            ->orderByDesc('activities.date')
            ->paginate(Pagination::MAX)
            ->withQueryString();
    }
}
