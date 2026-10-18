<?php

namespace App\Support;

use App\Http\Controllers\StudentController;
use App\Models\BadgeRequest;
use App\Models\Certificate;
use App\Models\LeadershipRecord;
use App\Models\Student;
use App\Models\User;
use App\Services\ParentLinkService;

/**
 * Data for the four-tab student record, shared by staff, family and self pages.
 */
final class StudentRecord
{
    public const TABS = ['profile' => 'Profile', 'certificates' => 'Certificates', 'badge-requests' => 'Badge requests', 'leadership' => 'Leadership'];

    /**
     * @param  string  $context  students | family | self
     * @return array<string, mixed>
     */
    public static function data(User $viewer, Student $student, string $tab, string $context): array
    {
        abort_unless(array_key_exists($tab, self::TABS), 404);

        $tabUrls = match ($context) {
            'self' => [
                'profile' => route('self.show'),
                'certificates' => route('self.certificates'),
                'badge-requests' => route('self.badge-requests'),
                'leadership' => route('self.leadership'),
            ],
            'family' => [
                'profile' => route('family.show', $student),
                'certificates' => route('family.student.certificates', $student),
                'badge-requests' => route('family.student.badge-requests', $student),
                'leadership' => route('family.student.leadership', $student),
            ],
            default => [
                'profile' => route('students.show', $student),
                'certificates' => route('students.certificates', $student),
                'badge-requests' => route('students.badge-requests', $student),
                'leadership' => route('students.leadership', $student),
            ],
        };

        $data = [
            'student' => $student,
            'tab' => $tab,
            'tabs' => self::TABS,
            'tabUrls' => $tabUrls,
            'context' => $context,
            'viewer' => $viewer,
        ];

        return $data + match ($tab) {
            'certificates' => ['certificates' => Certificate::query()->where('student_id', $student->id)->latest('date_awarded')->paginate(Pagination::MAX)],
            'badge-requests' => ['badgeRequests' => BadgeRequest::query()->where('student_id', $student->id)->latest()->paginate(Pagination::MAX)],
            'leadership' => ['leadershipRecords' => LeadershipRecord::query()->where('student_id', $student->id)->with('certificate')->latest('start_date')->paginate(Pagination::MAX)],
            default => [
                'groups' => StudentController::groupNames($student),
                'parent' => app(ParentLinkService::class)->assignedParent($student),
            ],
        };
    }
}
