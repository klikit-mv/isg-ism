<?php

namespace App\Http\Controllers;

use App\Enums\FeeStatus;
use App\Models\Activity;
use App\Models\ClassFee;
use App\Services\LeaderScopeService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassFeeController extends Controller
{
    public function __construct(private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $query = $this->query($request);

        $stats = (clone $query)->toBase()->selectRaw('COUNT(*) as records, SUM(CASE WHEN class_fees.status = ? THEN 1 ELSE 0 END) as paid, COALESCE(SUM(class_fees.amount), 0) as billed, COALESCE(SUM(class_fees.outstanding_amount), 0) as outstanding', [FeeStatus::Paid->value])->first();

        return view('finance.class-fees', [
            'fees' => $query->with('student', 'activity')->orderByDesc('class_fees.created_at')->paginate(Pagination::MAX)->withQueryString(),
            'stats' => [
                'records' => (int) $stats->records,
                'paid' => (int) $stats->paid,
                'billed' => Money::normalize($stats->billed),
                'outstanding' => Money::normalize($stats->outstanding),
            ],
            'activities' => $this->scope->constrainActivities(Activity::query()->where('charge_fee', true), $request->user())->orderByDesc('date')->pluck('name', 'uuid')->all(),
        ]);
    }

    /**
     * @return Builder<ClassFee>
     */
    private function query(Request $request): Builder
    {
        $query = ClassFee::query()
            ->select('class_fees.*')
            ->join('students', 'students.id', '=', 'class_fees.student_id')
            ->where('class_fees.status', '!=', FeeStatus::Void->value)
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('students.name', 'like', "%{$term}%")
                ->orWhere('students.national_id', 'like', "%{$term}%")
                ->orWhere('students.index_number', 'like', "%{$term}%")))
            ->when($request->query('activity'), fn ($q, $uuid) => $q->whereHas('activity', fn ($a) => $a->where('uuid', $uuid)))
            ->when($request->query('section'), fn ($q, $section) => $q->where('students.section', $section))
            ->when($request->query('status'), fn ($q, $status) => $q->where('class_fees.status', $status));

        return $this->scope->constrainByStudent($query, $request->user(), 'class_fees.student_id');
    }
}
