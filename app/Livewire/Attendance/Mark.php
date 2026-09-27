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
use App\Services\ClassFeeService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
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
     * Hook for work that runs after the register is saved.
     */
    protected function afterSave(Activity $activity): void {}

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

        $fees = $activity->charge_fee
            ? ClassFee::query()->where('activity_id', $activity->id)->get()->keyBy('student_id')
            : collect();

        return view('livewire.attendance.mark', [
            'activity' => $activity,
            'students' => $visible,
            'total' => $students->count(),
            'fees' => $fees,
            'choices' => app(ClassFeeService::class)->rosterPaymentChoices(),
        ]);
    }
}
