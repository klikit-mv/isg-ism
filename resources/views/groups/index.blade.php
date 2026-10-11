<x-app-layout title="Groups">
    <x-page-header title="Groups" description="Patrols, sixes and crews. Leaders see the scouts in the groups they lead.">
        <x-slot:actions>
            @can('create', \App\Models\Group::class)
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'create-group')">Create group</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Group name"/>
    </x-filters>

    @if ($groups->isEmpty())
        <x-empty message="No groups yet."/>
    @else
        <x-table :headers="[['label' => 'Group', 'sort' => 'name'], ['label' => 'Type', 'sort' => 'type'], ['label' => 'Section', 'sort' => 'section'], ['label' => 'Members', 'sort' => 'members'], ['label' => 'Leaders', 'sort' => 'leaders'], ['label' => 'Rover assistants', 'sort' => 'assistants'], ['label' => 'Status', 'sort' => 'status'], '']" default-sort="name:asc">
            @foreach ($groups as $group)
                <tr>
                    <td data-label="Group" class="font-medium"><a href="{{ route('groups.show', $group) }}" class="hover:underline">{{ $group->name }}</a></td>
                    <td data-label="Type">{{ $group->type ?: '—' }}</td>
                    <td data-label="Section">@if ($group->section)<x-badge :value="$group->section"/>@else<span class="text-xs text-gray-500">Mixed</span>@endif</td>
                    <td data-label="Members">{{ $group->members_count }}</td>
                    <td data-label="Leaders">{{ $group->leaders_count }}</td>
                    <td data-label="Rover assistants">{{ $group->assistant_leaders_count }}</td>
                    <td data-label="Status"><x-badge :value="$group->status"/></td>
                    <td class="text-right"><a href="{{ route('groups.show', $group) }}" class="link">Manage</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $groups->links() }}</div>
    @endif

    @can('create', \App\Models\Group::class)
        <x-modal name="create-group" title="Create group" maxWidth="md">
            <form method="POST" action="{{ route('groups.store') }}" class="space-y-4">
                @csrf
                <x-form.input name="name" label="Name" required/>
                <x-form.input name="type" label="Type" placeholder="Patrol, Six, Crew…"/>
                <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" placeholder="Mixed (any section)"/>
                <p class="-mt-2 text-xs text-gray-500">Only scouts from this section can be added as members.</p>
                <p class="text-xs text-gray-500">You become the owner and first leader.</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-group')">Cancel</button>
                    <button type="submit" class="btn-primary">Create</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
