<?php

namespace App\Livewire\Attendance;

use App\Enums\AttendanceStatus;
use App\Exceptions\ScoutException;
use App\Models\Activity;
use App\Models\AttendanceRecord;
use App\Models\ClassFee;
use App\Models\Payment;
use App\Models\Student;
use App\Services\ActivityRosterService;
use App\Services\AttendanceService;
use App\Services\CertificateService;
use App\Services\ClassFeeService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The attendance register for one activity.
 */
class Mark extends Component
{
    #[Locked]
    public int $activityId;

    /** @var array<int, array{status: string, remarks: string, payment: string, other: string}> */
    public array $rows = [];

    public string $search = '';

    public string $section = '';

    public string $statusFilter = '';

    public string $feeDue = '';

    public ?string $flash = null;

    public ?string $error = null;

    public function mount(Activity $activity): void
    {
        abort_unless(Auth::user()->can('markAttendance', $activity), 403);

        $this->activityId = $activity->id;
        $this->feeDue = $activity->charge_fee ? Money::normalize(app(ClassFeeService::class)->amountFor($activity)) : '';
        $this->loadRows();
    }

    private function activity(): Activity
    {
        return Activity::query()->findOrFail($this->activityId);
    }

    private function loadRows(): void
    {
        $activity = $this->activity();
        $ids = app(ActivityRosterService::class)->markableStudentIds($activity, Auth::user());
        $records = AttendanceRecord::query()->where('activity_id', $activity->id)->get()->keyBy('student_id');
        $fees = ClassFee::query()->where('activity_id', $activity->id)->get()->keyBy('student_id');
        $rosterPayments = Payment::query()
            ->where('payable_type', 'class_fee')
            ->whereIn('payable_id', $fees->pluck('id')->all() ?: [0])
            ->where('source', Payment::SOURCE_ROSTER)
            ->where('status', 'Paid')
            ->get()
            ->keyBy('payable_id');

        $this->rows = [];

        foreach ($ids as $id) {
            $record = $records->get($id);
            $fee = $fees->get($id);
            $paid = $fee ? $rosterPayments->get($fee->id)?->amount : null;
            $choice = '';
            $other = '';

            if ($paid !== null) {
                $whole = rtrim(rtrim((string) $paid, '0'), '.');
                in_array($whole, ['5', '10', '15'], true) ? $choice = $whole : [$choice, $other] = ['other', (string) $paid];
            } elseif ($record !== null && $fee !== null) {
                $choice = '0';
            }

            $this->rows[$id] = [
                'status' => $record?->status?->value ?? '',
                'remarks' => (string) ($record?->remarks ?? ''),
                'payment' => $choice,
                'other' => $other,
            ];
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->rows as $id => $row) {
            $this->rows[$id]['status'] = AttendanceStatus::Present->value;
        }
    }

    public function clear(): void
    {
        foreach ($this->rows as $id => $row) {
            $this->rows[$id] = ['status' => '', 'remarks' => '', 'payment' => '', 'other' => ''];
        }
    }

    public function updateFee(ClassFeeService $classFees): void
    {
        $this->resetMessages();
        $this->validate(['feeDue' => ['required', 'numeric', 'min:0']]);

        try {
            $count = $classFees->updateActivityFee($this->activity(), (string) $this->feeDue, Auth::user());
            $this->flash = "Fee updated. {$count} class fee(s) re-priced.";
        } catch (ScoutException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function save(AttendanceService $attendance): void
    {
        $this->resetMessages();
        $activity = $this->activity();
        $marks = [];

        foreach ($this->rows as $id => $row) {
            if ($row['status'] === '') {
                continue;
            }

            $payment = null;

            if ($activity->charge_fee && $row['payment'] !== '') {
                $payment = $row['payment'] === 'other' ? (string) $row['other'] : $row['payment'];

                if ($row['payment'] === 'other' && (! is_numeric($payment) || (float) $payment < 0)) {
                    $this->error = 'Enter a valid “Other” amount for every scout marked as Other.';

                    return;
                }
            }

            $marks[$id] = ['status' => $row['status'], 'remarks' => $row['remarks'], 'payment' => $payment];
        }

        try {
            $result = $attendance->mark($activity, Auth::user(), $marks);
        } catch (ScoutException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->flash = "Saved {$result['marked']} mark(s).";
        $this->afterSave($activity);
        $this->loadRows();
    }

    /**
     * Issue activity certificates after the register is committed. Failures
     * are reported without undoing the saved marks.
     */
    protected function afterSave(Activity $activity): void
    {
        if ($activity->certificate_template_id === null) {
            return;
        }

        try {
            $result = app(CertificateService::class)->issueForActivityAttendance($activity, Auth::user());
        } catch (\Throwable $e) {
            $this->error = 'Attendance was saved, but certificates could not be issued: '.$e->getMessage();

            return;
        }

        if ($result['issued'] > 0) {
            $this->flash .= " {$result['issued']} certificate(s) issued.";
        }

        if ($result['failed'] !== []) {
            $this->error = 'Attendance was saved, but '.count($result['failed']).' certificate(s) could not be issued. Use Issue on the Certificates page to retry.';
        }
    }

    /**
     * The visible scouts split by the sub-group they belong to (group order, then sub-group name), scouts without a
     * sub-group last. Each list keeps the name order.
     *
     * @param  Collection<int, Student>  $students
     * @return list<array{title: string, students: Collection<int, Student>}>
     */
    private function bySubgroup(Collection $students): array
    {
        $memberships = DB::table('group_members')
            ->join('groups', 'groups.id', '=', 'group_members.group_id')
            ->join('group_subgroups', 'group_subgroups.id', '=', 'group_members.subgroup_id')
            ->whereIn('group_members.student_id', $students->pluck('id')->all() ?: [0])
            ->orderBy('groups.name')->orderBy('group_subgroups.name')
            ->get(['group_members.student_id', 'groups.name as group_name', 'group_subgroups.name as subgroup_name'])
            ->unique('student_id')
            ->keyBy('student_id');

        $multipleGroups = $memberships->pluck('group_name')->unique()->count() > 1;
        $buckets = [];

        foreach ($students as $student) {
            $membership = $memberships->get($student->id);
            $title = $membership
                ? ($multipleGroups ? $membership->group_name.' · ' : '').$membership->subgroup_name
                : 'No sub-group';
            $buckets[$title][] = $student;
        }

        uksort($buckets, fn (string $a, string $b): int => ($a === 'No sub-group') <=> ($b === 'No sub-group') ?: strnatcasecmp($a, $b));

        return collect($buckets)->map(fn (array $list, string $title): array => ['title' => $title, 'students' => collect($list)])->values()->all();
    }

    private function resetMessages(): void
    {
        $this->flash = null;
        $this->error = null;
    }

    public function render(): View
    {
        $activity = $this->activity();
        $students = Student::query()->whereIn('id', array_keys($this->rows) ?: [0])->orderBy('name')->get();

        $visible = $students->filter(function (Student $student): bool {
            $row = $this->rows[$student->id] ?? null;

            if ($this->search !== '' && ! str_contains(mb_strtolower($student->name.' '.$student->index_number), mb_strtolower($this->search))) {
                return false;
            }

            if ($this->section !== '' && $student->section?->value !== $this->section) {
                return false;
            }

            if ($this->statusFilter === 'unmarked') {
                return ($row['status'] ?? '') === '';
            }

            return $this->statusFilter === '' || ($row['status'] ?? '') === $this->statusFilter;
        });

        $sections = $this->bySubgroup($visible);

        $fees = $activity->charge_fee
            ? ClassFee::query()->where('activity_id', $activity->id)->get()->keyBy('student_id')
            : collect();

        return view('livewire.attendance.mark', [
            'activity' => $activity,
            'students' => $visible,
            'sections' => $sections,
            'total' => $students->count(),
            'fees' => $fees,
            'choices' => app(ClassFeeService::class)->rosterPaymentChoices(),
        ]);
    }
}
