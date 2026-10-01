<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RoverAttendanceStatus;
use App\Models\User;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Six scoped reports. Totals always come from SQL aggregates over the whole
 * filtered set, never from the visible page.
 */
class ReportService
{
    public function __construct(private LeaderScopeService $scope) {}

    /**
     * @return array<string, array{title: string, description: string, filters: list<string>, statuses: array<string, string>, totals: list<string>}>
     */
    public function catalog(): array
    {
        return [
            'attendance' => [
                'title' => 'Attendance',
                'description' => 'Every attendance mark with activity, scout and who marked it.',
                'filters' => ['status', 'q', 'from', 'to'],
                'statuses' => AttendanceStatus::options(),
                'totals' => [],
            ],
            'rover-attendance' => [
                'title' => 'Rover attendance',
                'description' => 'Rover register marks, required and optional.',
                'filters' => ['status'],
                'statuses' => RoverAttendanceStatus::options(),
                'totals' => [],
            ],
            'annual-fees' => [
                'title' => 'Annual fees',
                'description' => 'Annual fees for scouts and leaders with balances.',
                'filters' => ['status', 'year', 'q'],
                'statuses' => FeeStatus::options(),
                'totals' => ['billed', 'paid', 'outstanding'],
            ],
            'class-fees' => [
                'title' => 'Class fees',
                'description' => 'Class fees from attendance, including voided ones.',
                'filters' => ['status', 'q'],
                'statuses' => FeeStatus::options(),
                'totals' => ['billed', 'paid', 'outstanding'],
            ],
            'payments' => [
                'title' => 'Payments',
                'description' => 'All payments with method, status and verifier.',
                'filters' => ['status', 'method', 'q'],
                'statuses' => PaymentStatus::options(),
                'totals' => ['amount'],
            ],
            'shop' => [
                'title' => 'Shop',
                'description' => 'Purchase lines with payment and order status.',
                'filters' => ['status'],
                'statuses' => PurchaseStatus::options(),
                'totals' => ['amount'],
            ],
        ];
    }

    public function exists(string $type): bool
    {
        return array_key_exists($type, $this->catalog());
    }

    /**
     * @return list<string>
     */
    public function headings(string $type): array
    {
        return match ($type) {
            'attendance' => ['Date', 'Activity', 'Student', 'Section', 'Status', 'Remarks', 'Marked by'],
            'rover-attendance' => ['Activity', 'Rover', 'Status', 'Required/Optional', 'Marked by', 'Date'],
            'annual-fees' => ['Year', 'Scout/Leader', 'Section', 'Fee', 'Paid', 'Outstanding', 'Status'],
            'class-fees' => ['Activity', 'Student', 'Fee', 'Paid', 'Outstanding', 'Status'],
            'payments' => ['Payment id', 'Type', 'Student', 'Amount', 'Method', 'Status', 'Submitted', 'Verified', 'Verifier'],
            'shop' => ['Purchase', 'Item', 'Quantity', 'Customer', 'Amount', 'Payment status', 'Purchase status', 'Date'],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(string $type, array $filters, User $user): Builder
    {
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $term = isset($filters['q']) ? '%'.$filters['q'].'%' : null;

        $query = match ($type) {
            'attendance' => DB::table('attendance_records as r')
                ->join('activities as a', 'a.id', '=', 'r.activity_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->leftJoin('users as m', 'm.id', '=', 'r.marked_by')
                ->select('a.date', 'a.name as activity', 's.name as student', 's.section', 'r.status', 'r.remarks', 'm.name as marked_by', 'r.student_id')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('r.status', $v))
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('a.name', 'like', $term)->orWhere('s.name', 'like', $term)))
                ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('a.date', '>=', $v))
                ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('a.date', '<=', $v))
                ->orderByDesc('a.date')->orderBy('s.name'),
            'rover-attendance' => DB::table('rover_attendance_records as r')
                ->join('activities as a', 'a.id', '=', 'r.activity_id')
                ->join('students as s', 's.id', '=', 'r.student_id')
                ->leftJoin('users as m', 'm.id', '=', 'r.marked_by')
                ->select('a.name as activity', 's.name as rover', 'r.status', 'r.is_required', 'm.name as marked_by', 'a.date', 'r.student_id')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('r.status', $v))
                ->orderByDesc('a.date')->orderBy('s.name'),
            'annual-fees' => DB::table('annual_fees as f')
                ->join('annual_fee_years as y', 'y.id', '=', 'f.annual_fee_year_id')
                ->leftJoin('students as s', 's.id', '=', 'f.student_id')
                ->leftJoin('users as u', 'u.id', '=', 'f.user_id')
                ->select('y.year', DB::raw('COALESCE(s.name, u.name) as person'), DB::raw('COALESCE(f.section, s.section) as section'), 'f.amount', 'f.paid_amount', 'f.outstanding_amount', 'f.status', 'f.student_id', 'f.user_id')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('f.status', $v))
                ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('y.year', $v))
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('s.name', 'like', $term)->orWhere('u.name', 'like', $term)))
                ->orderByDesc('y.year')->orderBy('person'),
            'class-fees' => DB::table('class_fees as f')
                ->join('activities as a', 'a.id', '=', 'f.activity_id')
                ->join('students as s', 's.id', '=', 'f.student_id')
                ->select('a.name as activity', 's.name as student', 'f.amount', 'f.paid_amount', 'f.outstanding_amount', 'f.status', 'f.student_id', 'a.date')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('f.status', $v))
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('a.name', 'like', $term)->orWhere('s.name', 'like', $term)))
                ->orderByDesc('a.date')->orderBy('s.name'),
            'payments' => DB::table('payments as p')
                ->leftJoin('students as s', 's.id', '=', 'p.student_id')
                ->leftJoin('users as v', 'v.id', '=', 'p.verified_by')
                ->select('p.uuid', 'p.payable_type', 's.name as student', 'p.amount', 'p.method', 'p.status', 'p.submitted_at', 'p.verified_at', 'v.name as verifier', 'p.student_id')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('p.status', $v))
                ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('p.method', $v))
                ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('p.uuid', 'like', $term)->orWhere('s.name', 'like', $term)))
                ->orderByDesc('p.submitted_at'),
            'shop' => DB::table('purchase_items as i')
                ->join('purchases as p', 'p.id', '=', 'i.purchase_id')
                ->join('students as s', 's.id', '=', 'p.student_id')
                ->select('p.uuid', 'i.item_name_snapshot as item', 'i.quantity', 's.name as customer', 'i.total_amount', 'p.payment_status', 'p.purchase_status', 'p.created_at', 'p.student_id')
                ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('p.purchase_status', $v))
                ->orderByDesc('p.created_at'),
        };

        return $this->scoped($type, $query, $user);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(string $type, array $filters, User $user): LengthAwarePaginator
    {
        return $this->query($type, $filters, $user)->paginate(Pagination::MAX)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int>
     */
    public function totals(string $type, array $filters, User $user): array
    {
        $base = $this->query($type, $filters, $user)->reorder();

        $row = match ($type) {
            'annual-fees', 'class-fees' => DB::query()->fromSub($base, 't')->selectRaw('COUNT(*) as rows_count, COALESCE(SUM(amount), 0) as billed, COALESCE(SUM(paid_amount), 0) as paid, COALESCE(SUM(outstanding_amount), 0) as outstanding')->first(),
            'payments' => DB::query()->fromSub($base, 't')->selectRaw('COUNT(*) as rows_count, COALESCE(SUM(amount), 0) as amount')->first(),
            'shop' => DB::query()->fromSub($base, 't')->selectRaw('COUNT(*) as rows_count, COALESCE(SUM(total_amount), 0) as amount')->first(),
            default => DB::query()->fromSub($base, 't')->selectRaw('COUNT(*) as rows_count')->first(),
        };

        $totals = ['rows' => (int) $row->rows_count];

        foreach (['billed', 'paid', 'outstanding', 'amount'] as $key) {
            if (isset($row->{$key})) {
                $totals[$key] = Money::normalize($row->{$key});
            }
        }

        return $totals;
    }

    /**
     * @return list<string>
     */
    public function mapRow(string $type, object $row): array
    {
        return match ($type) {
            'attendance' => [scout_date($row->date), $row->activity, $row->student, (string) $row->section, $row->status, (string) $row->remarks, (string) $row->marked_by],
            'rover-attendance' => [$row->activity, $row->rover, $row->status, $row->is_required ? 'Required' : 'Optional', (string) $row->marked_by, scout_date($row->date)],
            'annual-fees' => [(string) $row->year, (string) $row->person, (string) $row->section, Money::normalize($row->amount), Money::normalize($row->paid_amount), Money::normalize($row->outstanding_amount), (FeeStatus::tryFrom($row->status)?->label() ?? $row->status)],
            'class-fees' => [$row->activity, $row->student, Money::normalize($row->amount), Money::normalize($row->paid_amount), Money::normalize($row->outstanding_amount), (FeeStatus::tryFrom($row->status)?->label() ?? $row->status)],
            'payments' => [$row->uuid, str_replace('_', ' ', ucfirst($row->payable_type)), (string) $row->student, Money::normalize($row->amount), (PaymentMethod::tryFrom($row->method)?->label() ?? $row->method), (PaymentStatus::tryFrom($row->status)?->label() ?? $row->status), scout_datetime($row->submitted_at), scout_datetime($row->verified_at), (string) $row->verifier],
            'shop' => [substr($row->uuid, 0, 8), $row->item, (string) $row->quantity, $row->customer, Money::normalize($row->total_amount), (FeeStatus::tryFrom($row->payment_status)?->label() ?? $row->payment_status), (PurchaseStatus::tryFrom($row->purchase_status)?->label() ?? $row->purchase_status), scout_date($row->created_at)],
        };
    }

    /**
     * The totals row aligned with the headings.
     *
     * @param  array<string, string|int>  $totals
     * @return list<string>
     */
    public function totalsRow(string $type, array $totals): array
    {
        $row = array_fill(0, count($this->headings($type)), '');
        $row[0] = 'Total ('.$totals['rows'].' rows)';

        $positions = match ($type) {
            'annual-fees' => ['billed' => 3, 'paid' => 4, 'outstanding' => 5],
            'class-fees' => ['billed' => 2, 'paid' => 3, 'outstanding' => 4],
            'payments' => ['amount' => 3],
            'shop' => ['amount' => 4],
            default => [],
        };

        foreach ($positions as $key => $index) {
            $row[$index] = (string) $totals[$key];
        }

        return $row;
    }

    private function scoped(string $type, Builder $query, User $user): Builder
    {
        $ids = $this->scope->getLeaderStudentIds($user);

        if ($ids === null) {
            return $query;
        }

        $column = match ($type) {
            'attendance', 'rover-attendance' => 'r.student_id',
            'annual-fees', 'class-fees' => 'f.student_id',
            'payments' => 'p.student_id',
            'shop' => 'p.student_id',
        };

        if ($type === 'annual-fees') {
            return $query->where(fn ($w) => $w->whereIn($column, $ids === [] ? [0] : $ids)->orWhere('f.user_id', $user->id));
        }

        return $query->whereIn($column, $ids === [] ? [0] : $ids);
    }
}
