<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\AnnualFeeYear;
use App\Services\ReportService;
use App\Services\XlsxExportService;
use App\Support\YearFilter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->isStaff(), 403);

        return view('reports.index', ['catalog' => $this->reports->catalog()]);
    }

    public function show(Request $request, string $type): View
    {
        abort_unless($request->user()->isStaff(), 403);
        abort_unless($this->reports->exists($type), 404);

        $years = AnnualFeeYear::query()->orderByDesc('year')->pluck('year', 'year')->all();
        YearFilter::applyDefault($request, $years);
        $filters = $this->filters($request, $type);
        $print = $request->boolean('print');

        return view($print ? 'reports.print' : 'reports.show', [
            'type' => $type,
            'meta' => $this->reports->catalog()[$type],
            'headings' => $this->reports->headings($type),
            'rows' => $print ? $this->reports->query($type, $filters, $request->user())->limit(5000)->get() : $this->reports->paginate($type, $filters, $request->user()),
            'totals' => $this->reports->totals($type, $filters, $request->user()),
            'reports' => $this->reports,
            'years' => $years,
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function export(Request $request, string $type, XlsxExportService $exporter): BinaryFileResponse
    {
        abort_unless($request->user()->isStaff(), 403);
        abort_unless($this->reports->exists($type), 404);

        YearFilter::applyDefault($request, AnnualFeeYear::query()->pluck('year', 'year')->all());
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = $exporter->exportReport($type, $this->filters($request, $type), $request->user(), $format);

        return response()->download($path, $type.'-report-'.scout_now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, string $type): array
    {
        return $request->only($this->reports->catalog()[$type]['filters']);
    }
}
