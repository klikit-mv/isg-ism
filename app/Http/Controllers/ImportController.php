<?php

namespace App\Http\Controllers;

use App\Services\LegacyImportService;
use App\Support\Uploads;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Legacy workbook import (admin): upload → inspect → confirm.
 */
class ImportController extends Controller
{
    private const FILE_KEY = 'legacy_import_file';

    private const ERRORS_KEY = 'legacy_import_errors';

    public function __construct(private LegacyImportService $imports) {}

    public function index(): View
    {
        return view('import.index', [
            'inspection' => session('legacy_import_inspection'),
            'result' => session('legacy_import_result'),
            'hasErrors' => filled(session(self::ERRORS_KEY)),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:20480']]);

        $this->forgetUpload($request);
        $path = Uploads::store($request->file('file'), 'imports', 'legacy-'.Str::uuid().'.'.strtolower($request->file('file')->getClientOriginalExtension() ?: 'xlsx'), 'local');
        $request->session()->put(self::FILE_KEY, $path);

        $absolute = Storage::disk('local')->path($path);
        $inspection = $this->imports->inspect($absolute);
        $dryRun = $this->imports->import($absolute, true, $request->user());
        $request->session()->put(self::ERRORS_KEY, $dryRun['errors']);

        return redirect()->route('import.index')
            ->with('legacy_import_inspection', $inspection)
            ->with('legacy_import_result', $dryRun);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $path = $request->session()->get(self::FILE_KEY);
        abort_unless($path && Storage::disk('local')->exists($path), 422, 'Upload the workbook again to import it.');

        $result = $this->imports->import(Storage::disk('local')->path($path), false, $request->user());
        $request->session()->put(self::ERRORS_KEY, $result['errors']);
        $this->forgetUpload($request);

        $imported = array_sum(array_column($result['counts'], 'imported'));

        return redirect()->route('import.index')
            ->with('legacy_import_result', $result)
            ->with('success', "Import finished: {$imported} row(s) imported, ".count($result['errors']).' message(s).');
    }

    public function errors(Request $request): StreamedResponse
    {
        $errors = $request->session()->get(self::ERRORS_KEY, []);

        return response()->streamDownload(function () use ($errors): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sheet', 'Row', 'Message']);

            foreach ($errors as $error) {
                fputcsv($out, [$error['sheet'], $error['row'], $error['message']]);
            }

            fclose($out);
        }, 'import-errors-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function forgetUpload(Request $request): void
    {
        $old = $request->session()->pull(self::FILE_KEY);

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }
}
