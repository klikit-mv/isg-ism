<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\LeaderScopeService;
use App\Services\StudentPhotoService;
use App\Services\StudentService;
use App\Services\UserService;
use App\Support\Pagination;
use App\Support\Sort;
use App\Support\StudentRecord;
use App\Support\StudentValidation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('group') === 'none', fn ($q) => $q->whereDoesntHave('groups'));

        $this->scope->constrainStudents($query, $user);

        Sort::apply($query, $request, [
            'pending' => fn ($q, $dir) => $q->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StudentStatus::Pending->value])->orderBy('name'),
            'name' => 'name', 'index' => 'index_number', 'national_id' => 'national_id', 'section' => 'section', 'status' => 'status',
        ], 'pending');

        $students = $query->paginate(Pagination::MAX)->withQueryString();

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

        $destination = $request->user()->can('view', $result['student']) ? route('students.show', $result['student']) : route('students.index');

        return redirect($destination)->with('success', $message);
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

    public function resetPin(Request $request, Student $student, UserService $users): RedirectResponse
    {
        $this->authorize('resetPin', $student);
        abort_if($student->user === null, 422, 'This scout has no sign-in account.');

        $data = $request->validate(['pin' => ['nullable', 'string', 'min:4', 'max:32']]);
        $pin = filled($data['pin'] ?? null) ? (string) $data['pin'] : $this->students->temporaryPin();
        $users->resetPin($student->user, $pin, $request->user());

        $message = "{$student->name}'s PIN was reset and they were signed out everywhere.";

        if (blank($data['pin'] ?? null)) {
            $message .= " Their new temporary PIN is {$pin} — share it with them privately.";
        }

        return back()->with('success', $message);
    }

    public function changeStatus(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('update', $student);
        abort_unless($student->status !== StudentStatus::Pending, 422, 'Verify or decline a pending registration instead.');

        $data = $request->validate([
            'status' => ['required', Rule::in([StudentStatus::Active->value, StudentStatus::Inactive->value])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'effective_date' => ['required', 'date', 'before_or_equal:'.scout_today()],
        ]);

        $this->students->setStatus($student, StudentStatus::from($data['status']), $request->user(), trim($data['reason']), Carbon::parse($data['effective_date']));

        return back()->with('success', "{$student->name} is now {$student->status->value}.");
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
            ->leftJoin('group_subgroups', 'group_subgroups.id', '=', 'group_members.subgroup_id')
            ->whereNull('groups.deleted_at')
            ->where('group_members.student_id', $student->id)
            ->orderBy('groups.name')
            ->get(['groups.name as group', 'group_subgroups.name as subgroup'])
            ->map(fn ($row) => $row->subgroup ? "{$row->group} › {$row->subgroup}" : $row->group)
            ->all();
    }
}
