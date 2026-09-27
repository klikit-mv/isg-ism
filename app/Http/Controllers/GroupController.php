<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\ScoutSection;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use App\Services\GroupService;
use App\Services\LeaderScopeService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);

        $group = $this->groups->create($data['name'], $data['type'] ?? null, $request->user());

        return redirect()->route('groups.show', $group)->with('success', 'The group was created.');
    }

    public function show(Group $group): View
    {
        $this->authorize('view', $group);
        $group->load('members', 'leaders', 'assistantLeaders');

        $studentOptions = Student::query()->orderBy('name')->get(['id', 'name', 'section', 'index_number']);
        $leaderOptions = User::query()->active()
            ->whereHas('roleRows', fn ($q) => $q->whereIn('role', [Role::Leader->value, Role::Admin->value]))
            ->orderBy('name')->get(['id', 'name', 'national_id']);

        return view('groups.show', [
            'group' => $group,
            'memberOptions' => $studentOptions->map(fn ($s) => ['id' => $s->id, 'label' => $s->name, 'hint' => $s->section?->value])->all(),
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
        ]);

        $this->groups->rename($group, $data['name'], $data['type'] ?? null, $request->user());

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

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $this->authorize('delete', $group);
        $this->groups->delete($group, $request->user());

        return redirect()->route('groups.index')->with('success', 'The group was deleted.');
    }
}
