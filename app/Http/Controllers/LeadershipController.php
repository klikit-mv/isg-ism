<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Models\CertificateTemplate;
use App\Models\LeadershipRecord;
use App\Models\Student;
use App\Services\CertificateGenerationService;
use App\Services\LeaderScopeService;
use App\Services\LeadershipRecordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadershipController extends Controller
{
    public function __construct(private LeadershipRecordService $records, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LeadershipRecord::class);

        return view('leadership.index', ['records' => $this->records->paginate($request->user(), $request->only('q'))]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', LeadershipRecord::class);

        return view('leadership.form', ['record' => new LeadershipRecord(['troop_or_group' => config('scout.organisation')]), 'students' => $this->studentOptions($request)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorize('createFor', [LeadershipRecord::class, Student::query()->findOrFail($data['student_id'])]);

        $record = $this->records->create($data, $request->user());

        return redirect()->route('leadership.show', $record)->with('success', 'The leadership record was saved.');
    }

    public function show(LeadershipRecord $leadership): View
    {
        $this->authorize('view', $leadership);

        return view('leadership.show', [
            'record' => $leadership->load('student', 'certificate'),
            'templates' => CertificateTemplate::query()->where('type', CertificateType::Leadership->value)->where('active', true)->orderBy('name')->pluck('name', 'uuid')->all(),
        ]);
    }

    public function edit(Request $request, LeadershipRecord $leadership): View
    {
        $this->authorize('update', $leadership);

        return view('leadership.form', ['record' => $leadership, 'students' => $this->studentOptions($request)]);
    }

    public function update(Request $request, LeadershipRecord $leadership): RedirectResponse
    {
        $this->authorize('update', $leadership);
        $data = $this->validated($request);
        $this->authorize('createFor', [LeadershipRecord::class, Student::query()->findOrFail($data['student_id'])]);

        $this->records->update($leadership, $data, $request->user());

        return redirect()->route('leadership.show', $leadership)->with('success', 'The leadership record was saved.');
    }

    public function destroy(Request $request, LeadershipRecord $leadership): RedirectResponse
    {
        $this->authorize('delete', $leadership);
        $this->records->delete($leadership, $request->user());

        return redirect()->route('leadership.index')->with('success', 'The leadership record was deleted.');
    }

    public function generate(Request $request, LeadershipRecord $leadership, CertificateGenerationService $generator): RedirectResponse
    {
        $this->authorize('update', $leadership);

        if ($leadership->missingForCertificate() !== []) {
            return redirect()->route('leadership.edit', $leadership)->with('error', 'Enter '.implode(', ', $leadership->missingForCertificate()).' before generating the certificate.');
        }

        $data = $request->validate(['template' => ['nullable', 'uuid']]);
        $template = filled($data['template'] ?? null)
            ? CertificateTemplate::query()->where('uuid', $data['template'])->where('type', CertificateType::Leadership->value)->where('active', true)->first()
            : null;

        if (filled($data['template'] ?? null) && $template === null) {
            return back()->with('error', 'Choose an active leadership template.');
        }

        $certificate = $generator->generateLeadershipCertificate($leadership, $request->user(), $template);

        return redirect()->route('leadership.show', $leadership)->with('success', "Certificate {$certificate->cert_number} is ready.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'post' => ['required', 'string', 'max:255'],
            'patrol_or_six' => ['required', 'string', 'max:255'],
            'troop_or_group' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function studentOptions(Request $request): array
    {
        return $this->scope->constrainStudents(Student::query()->active(), $request->user())->orderBy('name')->pluck('name', 'id')->all();
    }
}
