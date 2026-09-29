<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\LeaderScopeService;
use App\Services\StudentPhotoService;
use App\Services\StudentService;
use App\Support\Pagination;
use App\Support\StudentRecord;
use App\Support\StudentValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(private StudentService $students, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $this->authorize('viewAny', Student::class);

        $query = Student::query()
            ->search($request->query('q'))
            ->when($request->query('section'), fn ($q, $section) => $q->where('section', $section))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status));

        $this->scope->constrainStudents($query, $user);

        $students = $query
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StudentStatus::Pending->value])
            ->orderBy('name')
            ->paginate(Pagination::MAX)
            ->withQueryString();

        $pendingCount = $user->isStaff() ? Student::query()->where('status', StudentStatus::Pending->value)->count() : 0;

        return view('students.index', compact('students', 'pendingCount'));
    }

    public function create(): View
    {
        $this->authorize('create', Student::class);

        return view('students.form', ['student' => new Student(['status' => StudentStatus::Active])]);
    }

    public function store(Request $request, StudentPhotoService $photos): RedirectResponse
    {
        $this->authorize('create', Student::class);
        $request->merge(StudentValidation::prepare($request->only('national_id')));
        $data = $request->validate(StudentValidation::rules() + ['photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120']]);

        $result = $this->students->create($data, $request->user());

        if ($request->hasFile('photo')) {
            $photos->assign($result['student'], $request->file('photo'));
        }

        $message = "{$result['student']->name} was enrolled.";

        if (blank($data['pin'] ?? null)) {
            $message .= " Their temporary PIN is {$result['pin']} — share it with them privately.";
        }

        return redirect()->route('students.show', $result['student'])->with('success', $message);
    }

    public function show(Request $request, Student $student, string $tab = 'profile'): View
    {
        $this->authorize('view', $student);

        return view('students.show', StudentRecord::data($request->user(), $student, $tab, 'students'));
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

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);

        return view('students.form', ['student' => $student]);
    }

    public function update(Request $request, Student $student, StudentPhotoService $photos): RedirectResponse
    {
        $this->authorize('update', $student);
        $request->merge(StudentValidation::prepare($request->only('national_id')));
        $data = $request->validate(StudentValidation::rules($student) + ['photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120']]);

        $this->students->update($student, $data, $request->user());

        if ($request->hasFile('photo')) {
            $photos->assign($student, $request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $photos->clear($student);
        }

        return redirect()->route('students.show', $student)->with('success', 'The scout was saved.');
    }

    public function destroy(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);
        $this->students->delete($student, $request->user());

        return redirect()->route('students.index')->with('success', 'The scout was deleted.');
    }

    public function photo(Request $request, Student $student, StudentPhotoService $photos): RedirectResponse
    {
        $this->authorize('photo', $student);

        if ($request->boolean('remove')) {
            $photos->clear($student);

            return back()->with('success', 'The photo was removed.');
        }

        $request->validate(['photo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120']]);
        $photos->assign($student, $request->file('photo'));

        return back()->with('success', 'The photo was updated.');
    }

    public function verify(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('verify', $student);
        abort_unless($student->status === StudentStatus::Pending, 422, 'Only pending registrations can be verified.');

        $this->students->verifyRegistration($student, $request->user());

        return back()->with('success', "{$student->name} is verified and can now sign in.");
    }

    public function reject(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('verify', $student);
        abort_unless($student->status === StudentStatus::Pending, 422, 'Only pending registrations can be declined.');

        $this->students->rejectRegistration($student, $request->user());

        return back()->with('success', "{$student->name}'s registration was declined.");
    }

    /**
     * Group names for a scout (used on the profile tab).
     *
     * @return list<string>
     */
    public static function groupNames(Student $student): array
    {
        return DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->whereNull('groups.deleted_at')
            ->where('group_members.student_id', $student->id)
            ->orderBy('groups.name')
            ->pluck('groups.name')
            ->all();
    }
}
