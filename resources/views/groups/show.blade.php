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
</x-app-layout>
