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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BadgeRequestController extends Controller
{
    public function __construct(private CertificateService $certificates, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $canBulk = $user->isActive() && $user->isLeader();

        return view('badge-requests.index', [
            'requests' => $this->certificates->accessibleRequests($user, $request->only('status', 'q')),
            'canBulk' => $canBulk,
            'bulk' => $canBulk ? [
                'badges' => Badge::query()->orderBy('name')->get()->map(fn (Badge $b) => ['id' => $b->uuid, 'name' => $b->name, 'section' => $b->section?->value])->values()->all(),
                'students' => $this->scope->constrainStudents(Student::query()->active(), $user)->orderBy('name')->get(['uuid', 'name', 'section', 'index_number'])
                    ->map(fn (Student $s) => ['id' => $s->uuid, 'name' => $s->name, 'section' => $s->section?->value, 'index' => $s->index_number])->values()->all(),
                'templates' => CertificateTemplate::query()->where('type', CertificateType::Badge->value)->where('active', true)->orderBy('name')->get(['uuid', 'name'])
                    ->map(fn (CertificateTemplate $t) => ['id' => $t->uuid, 'name' => $t->name])->values()->all(),
            ] : null,
        ]);
    }

    /**
     * The bulk window: one badge for many scouts, optionally approved and generated straight away. Answers with the
     * summary the window shows at the end.
     */
    public function bulk(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isActive() && $user->isLeader(), 403);

        $data = $request->validate([
            'badge' => ['required', 'uuid'],
            'students' => ['required', 'array', 'min:1', 'max:200'],
            'students.*' => ['uuid'],
            'approve' => ['boolean'],
            'date_awarded' => ['required_if:approve,1,true', 'nullable', 'date'],
            'template' => ['nullable', 'uuid'],
        ]);

        $badge = Badge::query()->where('uuid', $data['badge'])->firstOrFail();
        $template = filled($data['template'] ?? null)
            ? CertificateTemplate::query()->where('uuid', $data['template'])->where('type', CertificateType::Badge->value)->where('active', true)->first()
            : null;

        if (filled($data['template'] ?? null) && $template === null) {
            throw ValidationException::withMessages(['template' => 'Choose an active badge template.']);
        }

        $students = $this->scope->constrainStudents(Student::query()->active(), $user)->whereIn('uuid', $data['students'])->orderBy('name')->get();
        $rows = $this->certificates->bulkRequestBadge($badge, $students, $user, (bool) ($data['approve'] ?? false), $data['date_awarded'] ?? null, $template);
        $counts = collect($rows)->countBy('status');

        return response()->json([
            'badge' => $badge->name,
            'approve' => (bool) ($data['approve'] ?? false),
            'date_awarded' => $data['date_awarded'] ?? null,
            'rows' => $rows,
            'counts' => ['total' => count($rows), 'generated' => $counts['generated'] ?? 0, 'approved' => $counts['approved'] ?? 0, 'requested' => $counts['requested'] ?? 0, 'failed' => $counts['failed'] ?? 0],
        ]);
    }

    public function approveSelected(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'requests' => ['required', 'array', 'min:1', 'max:200'],
            'requests.*' => ['uuid'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['requests.required' => 'Select at least one request to approve.']);

        $result = $this->certificates->approveMany(BadgeRequest::query()->whereIn('uuid', $data['requests'])->with('student')->get(), $request->user(), $data['note'] ?? null);

        $message = "{$result['approved']} badge request(s) approved.";

        return back()->with($result['approved'] > 0 ? 'success' : 'error', $result['skipped'] > 0 ? $message." {$result['skipped']} skipped (already decided or outside your groups)." : $message);
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
