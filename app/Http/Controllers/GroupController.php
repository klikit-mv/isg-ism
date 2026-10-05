<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Models\Group;
use App\Models\Student;
use App\Models\Subgroup;
use App\Enums\RecordStatus;
use App\Models\User;
use App\Services\GroupService;
use App\Services\LeaderScopeService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function __construct(private GroupService $groups, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Group::class);
        $user = $request->user();

        $groups = Group::query()
            ->withCount(['members', 'leaders', 'assistantLeaders'])
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('id', $this->scope->getLeaderGroupIds($user) ?: [0]))
            ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->paginate(Pagination::MAX)
            ->withQueryString();

        return view('groups.index', ['groups' => $groups]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Group::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', Rule::enum(ScoutSection::class)],
        ]);

        $group = $this->groups->create($data['name'], $data['type'] ?? null, $request->user(), ScoutSection::tryFrom((string) ($data['section'] ?? '')));

        return redirect()->route('groups.show', $group)->with('success', 'The group was created.');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);
        $group->load('members', 'leaders', 'assistantLeaders', 'subgroups');

        $studentOptions = Student::query()->orderBy('name')->get(['id', 'name', 'section', 'index_number']);
        $memberPool = $group->section ? $studentOptions->where('section', $group->section)->values() : $studentOptions;
        $leaderOptions = User::query()->active()
            ->whereHas('roleRows', fn ($q) => $q->whereIn('role', [Role::Leader->value, Role::Admin->value]))
            ->orderBy('name')->get(['id', 'name', 'national_id']);

        return view('groups.show', [
            'group' => $group,
            'memberOptions' => $memberPool->map(fn ($s) => ['id' => $s->id, 'label' => $s->name, 'hint' => $s->section?->value])->all(),
            'leaderOptions' => $leaderOptions->map(fn ($u) => ['id' => $u->id, 'label' => $u->name, 'hint' => $u->national_id])->all(),
            'roverOptions' => $studentOptions->where('section', ScoutSection::Rover)->map(fn ($s) => ['id' => $s->id, 'label' => $s->name, 'hint' => $s->index_number])->values()->all(),
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', Rule::enum(ScoutSection::class)],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ]);

        $section = ScoutSection::tryFrom((string) ($data['section'] ?? ''));

        if ($section !== null && $group->members()->where('section', '!=', $section->value)->exists()) {
            return back()->with('error', "Some members are not in the {$section->value} section. Remove them first, then change the group's section.");
        }

        $this->groups->rename($group, $data['name'], $data['type'] ?? null, $request->user(), $section, RecordStatus::from($data['status']));

        return redirect()->route('groups.show', $group)->with('success', 'The group was saved.');
    }

    public function membership(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);
        $data = $request->validate([
            'members' => ['array'],
            'members.*' => ['integer'],
            'leaders' => ['array'],
            'leaders.*' => ['integer'],
            'assistant_leaders' => ['array'],
            'assistant_leaders.*' => ['integer'],
        ]);

        $this->groups->syncMembership($group, $data['members'] ?? [], $data['leaders'] ?? [], $data['assistant_leaders'] ?? [], $request->user());

        return redirect()->route('groups.show', $group)->with('success', 'Membership was saved.');
    }

    public function storeSubgroup(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $this->groups->addSubgroup($group, $data['name'], $request->user());

        return back()->with('success', 'The sub-group was added.');
    }

    public function updateSubgroup(Request $request, Group $group, Subgroup $subgroup): RedirectResponse
    {
        $this->authorize('update', $group);
        abort_unless($subgroup->group_id === $group->id, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $this->groups->renameSubgroup($subgroup, $data['name'], $request->user());

        return back()->with('success', 'The sub-group was renamed.');
    }

    public function destroySubgroup(Request $request, Group $group, Subgroup $subgroup): RedirectResponse
    {
        $this->authorize('update', $group);
        abort_unless($subgroup->group_id === $group->id, 404);
        $this->groups->deleteSubgroup($subgroup, $request->user());

        return back()->with('success', 'The sub-group was deleted. Its members now have no sub-group.');
    }

    public function assignSubgroups(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('update', $group);
        $data = $request->validate(['subgroup' => ['array'], 'subgroup.*' => ['nullable', 'integer']]);
        $this->groups->assignSubgroups($group, $data['subgroup'] ?? [], $request->user());

        return back()->with('success', 'Sub-groups were saved.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $this->groups->delete($group, $request->user());

        return redirect()->route('groups.index')->with('success', 'The group was deleted.');
    }
}
