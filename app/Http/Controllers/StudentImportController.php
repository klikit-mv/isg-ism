<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportController extends Controller
{
    private const SESSION_KEY = 'student_import_file';

    public function __construct(private StudentImportService $imports) {}

    public function index(): View
    {
        $this->authorize('import', Student::class);

        return view('students.import', ['report' => session('student_import_report')]);
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('import', Student::class);

        return response()->download($this->imports->template(), 'student-import-template.xlsx')->deleteFileAfterSend();
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->authorize('import', Student::class);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']]);

        $this->forgetUpload($request);
        $path = $request->file('file')->storeAs('imports', 'students-'.Str::uuid().'.'.strtolower($request->file('file')->getClientOriginalExtension() ?: 'xlsx'), 'local');
        $request->session()->put(self::SESSION_KEY, $path);

        $report = $this->imports->preview(Storage::disk('local')->path($path));

        return redirect()->route('students.import')->with('student_import_report', $report + ['preview' => true]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $this->authorize('import', Student::class);
        $path = $request->session()->get(self::SESSION_KEY);
        abort_unless($path && Storage::disk('local')->exists($path), 422, 'Upload the file again to import it.');

        $report = $this->imports->import(Storage::disk('local')->path($path), $request->user());
        $this->forgetUpload($request);

        return redirect()->route('students.import')
            ->with('student_import_report', $report + ['preview' => false])
            ->with('success', "{$report['created']} scout(s) enrolled, {$report['skipped']} skipped, {$report['errors']} with errors.");
    }

    private function forgetUpload(Request $request): void
    {
        $old = $request->session()->pull(self::SESSION_KEY);

        if ($old) {
            Storage::disk('local')->delete($old);
        }
    }
}
