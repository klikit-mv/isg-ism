<x-app-layout title="Leadership">
    <x-page-header title="Leadership records" description="Patrol and six leader appointments.">
        <x-slot:actions>
            @can('create', \App\Models\LeadershipRecord::class)
                <a href="{{ route('leadership.create') }}" class="btn-primary">Add record</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Scout, patrol or group"/>
    </x-filters>

    @if ($records->isEmpty())
        <x-empty message="No records."/>
    @else
        <x-table :headers="[['label' => 'Scout', 'sort' => 'scout'], ['label' => 'Post', 'sort' => 'post'], ['label' => 'Patrol or six', 'sort' => 'patrol'], ['label' => 'Troop or group', 'sort' => 'troop'], ['label' => 'Start', 'sort' => 'start'], ['label' => 'End', 'sort' => 'end'], ['label' => 'Certificate', 'sort' => 'certificate'], '']" default-sort="start:desc">
            @foreach ($records as $record)
                <tr>
                    <td data-label="Scout" class="font-medium">{{ $record->student?->name }}</td>
                    <td data-label="Post">{{ $record->post ?: '—' }}</td>
                    <td data-label="Patrol or six">{{ $record->patrol_or_six }}</td>
                    <td data-label="Troop or group">{{ $record->troop_or_group }}</td>
                    <td data-label="Start">{{ scout_date($record->start_date) }}</td>
                    <td data-label="End">{{ scout_date($record->end_date) ?: '—' }}</td>
                    <td data-label="Certificate" class="font-mono text-xs">{{ $record->certificate?->cert_number ?? '—' }}</td>
                    <td class="text-right"><a href="{{ route('leadership.show', $record) }}" class="link">Open</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $records->links() }}</div>
    @endif
</x-app-layout>
