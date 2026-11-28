<x-app-layout :title="$group->name">
    <x-page-header :title="$group->name" :description="($group->type ?: 'Group').' · '.($group->section?->value ?? 'Mixed sections').' · '.$group->members->count().' members'">
        <x-slot:actions>
            <a href="{{ route('groups.index') }}" class="btn-secondary">All groups</a>
            @can('delete', $group)
                <x-confirm :action="route('groups.destroy', $group)" method="DELETE" label="Delete group" size="md" message="Delete this group? Scouts stay in the registry." confirm="Delete"/>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('groups.update', $group) }}" class="card space-y-4">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">Details</h2>
            <x-form.input name="name" label="Name" :value="$group->name" required/>
            <x-form.input name="type" label="Type" :value="$group->type"/>
            <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="$group->section" placeholder="Mixed (any section)"/>
            <x-form.select name="status" label="Status" :options="\App\Enums\RecordStatus::options()" :value="$group->status" help="Inactive groups are not offered when creating activities."/>
            <button class="btn-primary btn-sm">Save</button>
        </form>

        <form method="POST" action="{{ route('groups.membership', $group) }}" class="card space-y-6 lg:col-span-2">
            @csrf
            @method('PUT')
            <h2 class="font-semibold">Membership</h2>
            @if ($group->section)
                <p class="text-sm text-gray-500 dark:text-gray-400">Showing {{ $group->section->value }} scouts only.</p>
            @endif
            <x-form.multi-pick name="members[]" label="Members" :options="$memberOptions" :selected="$group->members->pluck('id')->all()" placeholder="Search scouts"/>
            <x-form.multi-pick name="leaders[]" label="Leaders" :options="$leaderOptions" :selected="$group->leaders->pluck('id')->all()" placeholder="Search leaders"/>
            <x-form.multi-pick name="assistant_leaders[]" label="Rover assistant leaders" :options="$roverOptions" :selected="$group->assistantLeaders->pluck('id')->all()" placeholder="Search Rovers"/>
            <button type="submit" class="btn-primary">Save membership</button>
        </form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3" data-testid="subgroups">
        <div class="card space-y-4">
            <h2 class="font-semibold">Sub-groups</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Patrols, sixes or teams inside {{ $group->name }}.</p>
            @forelse ($group->subgroups as $subgroup)
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('groups.subgroups.update', [$group, $subgroup]) }}" class="flex flex-1 gap-2">
                        @csrf @method('PUT')
                        <input name="name" value="{{ $subgroup->name }}" class="input !py-1" required aria-label="Sub-group name">
                        <button class="btn-secondary btn-sm">Rename</button>
                    </form>
                    <x-confirm :action="route('groups.subgroups.destroy', [$group, $subgroup])" method="DELETE" label="Delete" message="Delete this sub-group? Its members stay in the group with no sub-group." confirm="Delete"/>
                </div>
            @empty
                <p class="text-sm text-gray-500">No sub-groups yet.</p>
            @endforelse
            <form method="POST" action="{{ route('groups.subgroups.store', $group) }}" class="flex gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                @csrf
                <input name="name" class="input" placeholder="New sub-group, e.g. Eagle" required aria-label="New sub-group name">
                <button class="btn-primary btn-sm">Add</button>
            </form>
        </div>

        <form method="POST" action="{{ route('groups.subgroups.assign', $group) }}" class="card lg:col-span-2">
            @csrf @method('PUT')
            <h2 class="mb-3 font-semibold">Which sub-group each member belongs to</h2>
            @if ($group->members->isEmpty())
                <p class="text-sm text-gray-500">Add members first (save membership above).</p>
            @elseif ($group->subgroups->isEmpty())
                <p class="text-sm text-gray-500">Add a sub-group first.</p>
            @else
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($group->members->sortBy('name') as $member)
                        <label class="flex items-center justify-between gap-2 rounded-lg border border-gray-100 p-2 text-sm dark:border-gray-700">
                            <span>{{ $member->name }}</span>
                            <select name="subgroup[{{ $member->id }}]" class="input !w-auto !py-1 text-xs">
                                <option value="">No sub-group</option>
                                @foreach ($group->subgroups as $subgroup)
                                    <option value="{{ $subgroup->id }}" @selected($member->pivot->subgroup_id === $subgroup->id)>{{ $subgroup->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
                <div class="mt-4"><button class="btn-primary">Save sub-groups</button></div>
            @endif
        </form>
    </div>
</x-app-layout>
