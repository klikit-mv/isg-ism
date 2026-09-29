<?php

namespace App\Http\Controllers;

use App\Enums\CertificateType;
use App\Enums\ScoutSection;
use App\Models\Activity;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Services\ActivityService;
use App\Services\LeaderScopeService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __construct(private ActivityService $activities, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()->with('groups', 'certificateTemplate')->withCount('attendanceRecords')
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('date', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('date', '<=', $to))
            ->when($request->query('charged') !== null && $request->query('charged') !== '', fn ($q) => $q->where('charge_fee', $request->query('charged') === 'yes'))
            ->when($request->query('certificate') === 'yes', fn ($q) => $q->whereNotNull('certificate_template_id'))
            ->when($request->query('certificate') === 'no', fn ($q) => $q->whereNull('certificate_template_id'));

        $this->scope->constrainActivities($query, $request->user());

        return view('activities.index', [
            'activities' => $query->orderByDesc('date')->paginate(Pagination::MAX)->withQueryString(),
        ] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Activity::class);
        $activity = $this->activities->create($this->validated($request), $request->user());

        return redirect()->route('activities.index')->with('success', "{$activity->name} was created and the roster was notified.");
    }

    public function edit(Activity $activity): View
    {
        $this->authorize('update', $activity);

        return view('activities.edit', ['activity' => $activity->load('groups')] + $this->formOptions());
    }

    public function update(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('update', $activity);
        $this->activities->update($activity, $this->validated($request), $request->user());

        return redirect()->route('activities.index')->with('success', 'The activity was saved.');
    }

    public function destroy(Request $request, Activity $activity): RedirectResponse
    {
        $this->authorize('delete', $activity);
        $this->activities->delete($activity, $request->user());

        return redirect()->route('activities.index')->with('success', 'The activity was deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'details' => ['nullable', 'string', 'max:5000'],
            'all_students' => ['sometimes', 'boolean'],
            'sections' => ['array'],
            'sections.*' => [Rule::enum(ScoutSection::class)],
            'groups' => ['array'],
            'groups.*' => ['integer', 'exists:groups,id'],
            'charge_fee' => ['sometimes', 'boolean'],
            'fee_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'certificate_template_id' => ['nullable', 'integer', Rule::exists('certificate_templates', 'id')->where('type', CertificateType::General->value)],
        ]);

        $data['all_students'] = $request->boolean('all_students');
        $data['charge_fee'] = $request->boolean('charge_fee');

        if (! $data['all_students'] && empty($data['sections']) && empty($data['groups'])) {
            throw ValidationException::withMessages(['sections' => 'Choose who is expected: all scouts, sections or groups.']);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'groupOptions' => Group::query()->orderBy('name')->get()->map(fn ($g) => ['id' => $g->id, 'label' => $g->name, 'hint' => $g->type])->all(),
            'templateOptions' => CertificateTemplate::query()->where('type', CertificateType::General->value)->where('active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
