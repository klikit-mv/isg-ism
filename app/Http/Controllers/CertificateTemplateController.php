<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Models\Activity;
use App\Models\CertificateTemplate;
use App\Services\CertificateGenerationService;
use App\Services\CertificateTemplateService;
use App\Services\Google\GoogleSlideExporter;
use App\Support\GoogleSlide;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CertificateTemplateController extends Controller
{
    public function __construct(private CertificateTemplateService $templates) {}

    public function index(): View
    {
        $this->authorize('viewAny', CertificateTemplate::class);

        return view('certificate-templates.index', [
            'templates' => CertificateTemplate::query()->with('activity')->withCount('certificates')->orderBy('type')->orderBy('name')->paginate(Pagination::MAX),
            'activities' => Activity::query()->orderByDesc('date')->limit(200)->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.' ('.scout_date($a->date).')'])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CertificateTemplate::class);
        $this->templates->create($this->validated($request), $request->user());

        return back()->with('success', 'The template was created.');
    }

    public function update(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->authorize('update', $certificateTemplate);
        $this->templates->update($certificateTemplate, $this->validated($request), $request->user());

        return back()->with('success', 'The template was saved.');
    }

    public function destroy(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->authorize('delete', $certificateTemplate);
        $this->templates->delete($certificateTemplate, $request->user());

        return back()->with('success', 'The template was deleted.');
    }

    public function activate(Request $request, CertificateTemplate $certificateTemplate): RedirectResponse
    {
        $this->authorize('update', $certificateTemplate);
        $this->templates->setActive($certificateTemplate, $request->boolean('active'), $request->user());

        return back()->with('success', 'The template is now '.($request->boolean('active') ? 'active' : 'inactive').'.');
    }

    public function preview(CertificateTemplate $certificateTemplate, CertificateGenerationService $generator): Response
    {
        $this->authorize('view', $certificateTemplate);

        return response($generator->previewTemplate($certificateTemplate))
            ->header('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'")
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function testSlide(Request $request, GoogleSlideExporter $slides): JsonResponse|RedirectResponse
    {
        $this->authorize('create', CertificateTemplate::class);
        $data = $request->validate(['google_slide' => ['required', 'string', 'max:500']]);
        $id = GoogleSlide::idFrom($data['google_slide']);

        $result = $id === null || str_starts_with($id, 'local-')
            ? ['ok' => false, 'message' => 'That does not look like a Google Slides link or presentation ID.']
            : $slides->testPresentation($id);

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(CertificateType::class)],
            'google_slide' => ['nullable', 'string', 'max:500'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (($data['activity_id'] ?? null) && $data['type'] !== CertificateType::General->value) {
            throw ValidationException::withMessages(['activity_id' => 'Only general templates can be linked to an activity.']);
        }

        $data['active'] = $request->boolean('active', true);

        return $data;
    }
}
