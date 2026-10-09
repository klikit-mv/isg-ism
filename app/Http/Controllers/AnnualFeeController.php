<?php

namespace App\Http\Controllers;

use App\Enums\FeeStatus;
use App\Enums\Permission;
use App\Enums\PersonType;
use App\Enums\RecordStatus;
use App\Enums\ScoutSection;
use App\Models\AnnualFee;
use App\Models\AnnualFeeYear;
use App\Services\AnnualFeeService;
use App\Services\LeaderScopeService;
use App\Support\Money;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnualFeeController extends Controller
{
    public function __construct(private AnnualFeeService $fees, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $ids = $this->scope->getLeaderStudentIds($user);

        $query = AnnualFee::query()
            ->select('annual_fees.*')
            ->leftJoin('students', 'students.id', '=', 'annual_fees.student_id')
            ->leftJoin('users', 'users.id', '=', 'annual_fees.user_id')
            ->when($ids !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('annual_fees.student_id', $ids === [] ? [0] : $ids)
                ->orWhere('annual_fees.user_id', $user->id)))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('students.name', 'like', "%{$term}%")->orWhere('students.national_id', 'like', "%{$term}%")
                ->orWhere('users.name', 'like', "%{$term}%")->orWhere('users.national_id', 'like', "%{$term}%")))
            ->when($request->query('year'), fn ($q, $year) => $q->whereHas('feeYear', fn ($y) => $y->where('year', $year)))
            ->when($request->query('section'), fn ($q, $section) => $q->where(fn ($w) => $w
                ->where('annual_fees.section', $section)
                ->orWhere(fn ($x) => $x->whereNull('annual_fees.section')->where('students.section', $section))))
            ->when($request->query('status'), fn ($q, $status) => $q->where('annual_fees.status', $status));

        $stats = (clone $query)->toBase()->reorder()->select([])->selectRaw('COUNT(*) as records, SUM(CASE WHEN annual_fees.status = ? THEN 1 ELSE 0 END) as paid, COALESCE(SUM(annual_fees.amount), 0) as billed', [FeeStatus::Paid->value])->first();

        return view('finance.annual-fees', [
            'fees' => $query->with('student', 'user', 'feeYear')->orderByDesc('annual_fees.created_at')->paginate(Pagination::MAX)->withQueryString(),
            'stats' => ['records' => (int) $stats->records, 'paid' => (int) $stats->paid, 'billed' => Money::normalize($stats->billed)],
            'years' => AnnualFeeYear::query()->orderByDesc('year')->pluck('year', 'year')->all(),
            'canManageYears' => $user->hasPermission(Permission::ManageFees),
        ]);
    }

    public function years(Request $request): View
    {
        $this->authorizeFees($request);

        return view('finance.annual-fee-years', [
            'years' => AnnualFeeYear::query()->withCount('fees')->orderByDesc('year')->get()->each(fn (AnnualFeeYear $y) => $y->setAttribute('scouts_without_invoice', $y->status === RecordStatus::Active ? $this->fees->scoutsWithoutInvoice($y) : 0)),
            'suggestedYear' => $this->fees->suggestedYear(),
        ]);
    }

    public function storeYear(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2200', Rule::unique('annual_fee_years', 'year')],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999'],
        ]);

        $year = $this->fees->createYear((int) $data['year'], Money::normalize($data['amount']), $request->user());
        $message = "Fee year {$data['year']} was created.";

        if ($request->boolean('invoice_everyone') && $request->user()->hasPermission(Permission::ManageFees)) {
            $result = $this->fees->generateForEveryone($year, $request->user());
            $message .= " {$result['created']} active scout(s) were invoiced.";
        }

        return redirect()->route('annual-fees.years')->with('success', $message);
    }

    public function yearStatus(Request $request, AnnualFeeYear $year): RedirectResponse
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate(['status' => ['required', Rule::enum(RecordStatus::class)]]);

        $this->fees->setYearStatus($year, RecordStatus::from($data['status']), $request->user());

        return back()->with('success', "Fee year {$year->year} is now {$data['status']}.");
    }

    public function generateForm(Request $request, AnnualFeeYear $year): View
    {
        $this->authorizeFees($request);

        return view('finance.annual-fee-generate', [
            'year' => $year,
            'people' => $this->fees->invoicePeople($year, $request->boolean('inactive')),
        ]);
    }

    public function generate(Request $request, AnnualFeeYear $year): RedirectResponse
    {
        $this->authorizeFees($request);

        $data = $request->validate([
            'people' => ['required', 'array', 'min:1'],
            'people.*' => ['string', 'regex:/^[su]\d+$/'],
            'sections' => ['array'],
            'sections.*' => ['nullable', Rule::enum(ScoutSection::class)],
        ]);

        $people = array_map(fn (string $key) => [
            'type' => $key[0] === 'u' ? PersonType::Leader->value : PersonType::Student->value,
            'id' => (int) substr($key, 1),
            'section' => $data['sections'][$key] ?? null,
        ], $data['people']);

        $result = $this->fees->generate($year, $people, $request->user());

        return redirect()->route('annual-fees.years')->with('success', "{$result['created']} invoice(s) created, {$result['skipped']} skipped (already invoiced).");
    }

    public function generateAll(Request $request, AnnualFeeYear $year): RedirectResponse
    {
        $this->authorizeFees($request);
        $result = $this->fees->generateForEveryone($year, $request->user(), $request->boolean('include_leaders'));

        return redirect()->route('annual-fees.years')->with('success', "{$result['created']} invoice(s) created for {$year->year}, {$result['skipped']} skipped (already invoiced).");
    }

    /**
     * Read an Excel/CSV list, match its names to people and show what would be invoiced. Nothing is saved until the
     * preview is confirmed (it posts to the normal generate step).
     */
    public function importPreview(Request $request, AnnualFeeYear $year): View
    {
        $this->authorizeFees($request);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'], 'inactive' => ['nullable', 'boolean']]);

        $file = $request->file('file');
        $copy = sys_get_temp_dir().'/'.Str::uuid().'.'.(strtolower($file->getClientOriginalExtension()) ?: 'xlsx');
        copy($file->getRealPath() ?: $file->getPathname(), $copy);

        try {
            $match = $this->fees->matchSpreadsheet($year, $copy, $request->boolean('inactive'));
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'That file could not be read. Use an .xlsx, .xls or .csv file with a heading row.']);
        } finally {
            @unlink($copy);
        }

        return view('finance.annual-fee-import-preview', ['year' => $year, 'match' => $match, 'fileName' => $file->getClientOriginalName()]);
    }

    private function authorizeFees(Request $request): void
    {
        abort_unless($request->user()->hasPermission(Permission::ManageFees), 403);
    }
}
