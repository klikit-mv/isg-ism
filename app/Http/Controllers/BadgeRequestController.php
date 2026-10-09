<?php

namespace App\Http\Controllers;

use App\Enums\BadgeRequestStatus;
use App\Enums\CertificateType;
use App\Models\Badge;
use App\Models\BadgeRequest;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Services\CertificateService;
use App\Services\LeaderScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BadgeRequestController extends Controller
{
    public function __construct(private CertificateService $certificates, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        return view('badge-requests.index', [
            'requests' => $this->certificates->accessibleRequests($request->user(), $request->only('status', 'q')),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $students = $this->scope->constrainStudents(Student::query()->active(), $user)
            ->when($request->query('section'), fn ($q, $section) => $q->where('section', $section))
            ->orderBy('name')
            ->get(['uuid', 'name', 'section']);

        return view('badge-requests.create', [
            'students' => $students->mapWithKeys(fn ($s) => [$s->uuid => $s->name.' ('.$s->section?->value.')'])->all(),
            'badges' => Badge::query()
                ->when($request->query('section'), fn ($q, $section) => $q->where(fn ($w) => $w->where('section', $section)->orWhereNull('section')))
                ->orderBy('name')->get()->mapWithKeys(fn ($b) => [$b->uuid => $b->name.($b->section ? ' — '.$b->section->value : '')])->all(),
            'selectedStudent' => $request->query('student'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['student' => ['required', 'uuid'], 'badge' => ['required', 'uuid']]);

        $student = Student::query()->where('uuid', $data['student'])->firstOrFail();
        $badge = Badge::query()->where('uuid', $data['badge'])->firstOrFail();
        $badgeRequest = $this->certificates->requestBadge($student, $badge, $request->user());

        return redirect()->route('badge-requests.show', $badgeRequest)->with('success', "Request {$badgeRequest->request_id} was sent for approval.");
    }

    public function show(BadgeRequest $badgeRequest): View
    {
        $this->authorize('view', $badgeRequest);

        return view('badge-requests.show', [
            'badgeRequest' => $badgeRequest->load('student', 'badge', 'requester', 'reviewer', 'certificate'),
            'templates' => CertificateTemplate::query()->where('type', CertificateType::Badge->value)->where('active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function approve(Request $request, BadgeRequest $badgeRequest): RedirectResponse
    {
        $this->authorize('decide', $badgeRequest);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $this->certificates->approve($badgeRequest, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'The badge request was approved.');
    }

    public function reject(Request $request, BadgeRequest $badgeRequest): RedirectResponse
    {
        $this->authorize('decide', $badgeRequest);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);
        $this->certificates->reject($badgeRequest, $request->user(), $data['note'] ?? null);

        return back()->with('success', 'The badge request was rejected.');
    }

    public function generate(Request $request, BadgeRequest $badgeRequest): RedirectResponse
    {
        $this->authorize('decide', $badgeRequest);
        $data = $request->validate(['date_awarded' => ['required', 'date'], 'template' => ['nullable', 'uuid']]);

        $template = filled($data['template'] ?? null) ? CertificateTemplate::query()->where('uuid', $data['template'])->firstOrFail() : null;
        $certificate = $this->certificates->generateApproved($badgeRequest, $request->user(), $data['date_awarded'], $template);

        return redirect()->route('certificates.show', $certificate)->with('success', "Certificate {$certificate->cert_number} was generated.");
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return BadgeRequestStatus::values();
    }
}
