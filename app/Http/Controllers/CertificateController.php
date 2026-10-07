<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Enums\ScoutSection;
use App\Models\Activity;
use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CertificateGenerationService;
use App\Services\CertificateService;
use App\Services\GoogleDriveCertificateService;
use App\Services\LeaderScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateService $certificates,
        private CertificateGenerationService $generator,
        private GoogleDriveCertificateService $storage,
        private AuditLogService $audit,
        private LeaderScopeService $scope,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->only(['q', 'student', 'type', 'status', 'badge', 'from', 'to', 'sort']);

        return view('certificates.index', [
            'certificates' => $this->certificates->paginateCertificates($user, $filters),
            'stats' => $this->certificates->dashboardStats($user),
            'activitySummaries' => $user->isStaff() ? $this->certificates->activityCertificateSummaries($user) : collect(),
            'badges' => Badge::query()->orderBy('name')->pluck('name', 'uuid')->all(),
            'students' => $this->studentOptions($request),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Certificate::class);

        return view('certificates.create', [
            'students' => $this->studentOptions($request),
            'templates' => $this->generalTemplates(),
            'activities' => $this->scope->constrainActivities(Activity::query()->whereNotNull('certificate_template_id'), $request->user())->orderByDesc('date')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        $data = $request->validate([
            'student' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'date_awarded' => ['required', 'date'],
            'template' => ['nullable', 'uuid', 'required_without:activity'],
            'activity' => ['nullable', 'uuid'],
        ]);

        $student = Student::query()->where('uuid', $data['student'])->firstOrFail();
        abort_unless($this->scope->canAccessStudent($request->user(), $student), 403);

        $activity = filled($data['activity'] ?? null) ? Activity::query()->where('uuid', $data['activity'])->firstOrFail() : null;
        $template = filled($data['template'] ?? null) ? CertificateTemplate::query()->where('uuid', $data['template'])->firstOrFail() : null;

        $certificate = $this->generator->generateGeneralCertificate($student, $data['title'], $data['date_awarded'], $template, $request->user(), $activity);

        return redirect()->route('certificates.show', $certificate)->with('success', "Certificate {$certificate->cert_number} was issued.");
    }

    public function bulkCreate(Request $request): View
    {
        $this->authorize('create', Certificate::class);

        $query = Student::query()->active()->orderBy('name')
            ->when($request->query('section'), fn ($q, $section) => $q->where('section', $section));

        return view('certificates.bulk-create', [
            'students' => $this->scope->constrainStudents($query, $request->user())->get(),
            'templates' => $this->generalTemplates(),
        ]);
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        $data = $request->validate([
            'students' => ['required', 'array', 'min:1'],
            'students.*' => ['integer'],
            'title' => ['required', 'string', 'max:255'],
            'date_awarded' => ['required', 'date'],
            'template' => ['required', 'uuid'],
        ]);

        $template = CertificateTemplate::query()->where('uuid', $data['template'])->firstOrFail();
        $result = $this->certificates->bulkCreateGeneral($data['students'], $data['title'], $data['date_awarded'], $template, $request->user());

        $message = count($result['created']).' certificate(s) issued.';
        $redirect = redirect()->route('certificates.index')->with('success', $message);

        if ($result['failed'] !== []) {
            $lines = collect($result['failed'])->map(fn ($reason, $name) => "{$name}: {$reason}")->implode('; ');
            $redirect->with('warning', count($result['failed']).' could not be issued — '.$lines);
        }

        return $redirect;
    }

    public function show(Certificate $certificate): View
    {
        $this->authorize('view', $certificate);

        return view('certificates.show', ['certificate' => $certificate->load('student', 'verifier', 'template', 'activity')]);
    }

    public function preview(Certificate $certificate): Response
    {
        $this->authorize('view', $certificate);

        return $this->previewResponse($certificate);
    }

    /**
     * The certificate as generated (the PDF made from the Google Slides or built-in template) shown inline. Only when no PDF
     * has been stored yet is the built-in HTML layout drawn.
     */
    public function previewResponse(Certificate $certificate): Response
    {
        $pdf = $this->storage->contents($certificate->path);

        if ($pdf !== null && str_starts_with($pdf, '%PDF')) {
            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$certificate->cert_number.'.pdf"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return response($this->generator->previewHtml($certificate))
            ->header('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'")
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function download(Certificate $certificate): Response
    {
        $this->authorize('view', $certificate);

        return $this->pdfResponse($certificate);
    }

    public function bulkDownload(Request $request): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('bulkDownload', Certificate::class);
        $data = $request->validate(['certificates' => ['required', 'array', 'min:1', 'max:200'], 'certificates.*' => ['uuid']]);

        $certificates = $this->certificates->certificateQuery($request->user())->whereIn('certificates.uuid', $data['certificates'])->get();
        $file = tempnam(sys_get_temp_dir(), 'certs');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::OVERWRITE);
        $added = 0;

        foreach ($certificates as $certificate) {
            $pdf = $this->storage->contents($certificate->path);

            if ($pdf !== null) {
                $zip->addFromString($certificate->cert_number.'.pdf', $pdf);
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($file);

            return back()->with('error', 'None of the selected certificates have a PDF yet.');
        }

        $this->audit->record('certificate.bulk_downloaded', null, ['count' => $added]);

        return response()->download($file, 'certificates-'.now()->format('Ymd-His').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    public function issueActivity(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('create', Certificate::class);
        abort_unless($this->scope->canAccessActivity($request->user(), $activity), 403);

        $result = $this->certificates->issueForActivityAttendance($activity, $request->user());
        $redirect = back()->with('success', "{$result['issued']} certificate(s) issued for {$activity->name}.");

        if ($result['failed'] !== []) {
            $redirect->with('warning', count($result['failed']).' could not be issued: '.implode('; ', array_unique($result['failed'])));
        }

        return $redirect;
    }

    public function regenerate(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorize('manage', $certificate);
        $this->generator->regenerate($certificate, $request->user());

        return back()->with('success', 'The certificate PDF was regenerated.');
    }

    /**
     * Stream a certificate PDF, regenerating it if the stored file is missing.
     */
    public function pdfResponse(Certificate $certificate): Response
    {
        $pdf = $this->storage->contents($certificate->path);

        if ($pdf === null) {
            $this->generator->regenerate($certificate, auth()->user() ?? $certificate->student->user ?? new User);
            $pdf = $this->storage->contents($certificate->fresh()->path);
        }

        abort_if($pdf === null, 404);
        $this->audit->record('certificate.downloaded', $certificate, ['cert_number' => $certificate->cert_number]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$certificate->cert_number.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function generalTemplates(): array
    {
        return CertificateTemplate::query()->where('type', CertificateType::General->value)->where('active', true)->orderBy('name')->pluck('name', 'uuid')->all();
    }

    /**
     * @return array<string, string>
     */
    private function studentOptions(Request $request): array
    {
        return $this->scope->constrainStudents(Student::query(), $request->user())->orderBy('name')->get(['uuid', 'name', 'section'])
            ->mapWithKeys(fn (Student $s) => [$s->uuid => $s->name.' ('.($s->section?->value ?? '').')'])
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function sections(): array
    {
        return ScoutSection::values();
    }
}
