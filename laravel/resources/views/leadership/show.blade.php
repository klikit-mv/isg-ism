<x-app-layout title="Leadership record">
    <x-page-header :title="$record->patrol_or_six" :description="$record->student?->name.' · '.$record->troop_or_group">
        <x-slot:actions>
            <a href="{{ route('leadership.index') }}" class="btn-secondary">All records</a>
            @can('update', $record)
                <a href="{{ route('leadership.edit', $record) }}" class="btn-secondary">Edit</a>
                <form method="POST" action="{{ route('leadership.generate', $record) }}">@csrf<button class="btn-primary">{{ $record->certificate ? 'Regenerate certificate' : 'Generate certificate' }}</button></form>
            @endcan
            @can('delete', $record)
                <x-confirm :action="route('leadership.destroy', $record)" method="DELETE" label="Delete" size="md" message="Delete this leadership record?" confirm="Delete"/>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card max-w-2xl">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Scout</dt><dd>{{ $record->student?->name }}</dd></div>
            <div><dt class="text-gray-500">Patrol or six</dt><dd>{{ $record->patrol_or_six }}</dd></div>
            <div><dt class="text-gray-500">Troop or group</dt><dd>{{ $record->troop_or_group }}</dd></div>
            <div><dt class="text-gray-500">Start</dt><dd>{{ scout_long_date($record->start_date) }}</dd></div>
            <div><dt class="text-gray-500">End</dt><dd>{{ scout_long_date($record->end_date) ?: '—' }}</dd></div>
            <div><dt class="text-gray-500">Certificate</dt><dd>
                @if ($record->certificate)
                    <a href="{{ route('certificates.show', $record->certificate) }}" class="link font-mono">{{ $record->certificate->cert_number }}</a>
                @else — @endif
            </dd></div>
        </dl>
    </div>
</x-app-layout>
