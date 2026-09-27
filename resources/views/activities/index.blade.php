<x-app-layout title="Activities">
    <x-page-header title="Activities" description="Dated sessions with a target roster.">
        <x-slot:actions>
            @can('create', \App\Models\Activity::class)
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'create-activity')">Create activity</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Name" :value="request('q')"/>
        <x-form.input name="from" label="From" type="date" :value="request('from')"/>
        <x-form.input name="to" label="To" type="date" :value="request('to')"/>
        <x-form.select name="charged" label="Charged" :options="['yes' => 'Yes', 'no' => 'No']" :value="request('charged')" placeholder="Any"/>
        <x-form.select name="certificate" label="Has certificate" :options="['yes' => 'Yes', 'no' => 'No']" :value="request('certificate')" placeholder="Any"/>
    </x-filters>

    @if ($activities->isEmpty())
        <x-empty message="No activities match these filters."/>
    @else
        <x-table :headers="['Date', 'Activity', 'Roster', 'Fee', 'Certificate', 'Marked', '']">
            @foreach ($activities as $activity)
                <tr>
                    <td data-label="Date" class="whitespace-nowrap">{{ scout_date($activity->date) }}</td>
                    <td data-label="Activity" class="font-medium">{{ $activity->name }}</td>
                    <td data-label="Roster" class="text-xs">{{ $activity->targetSummary() }}</td>
                    <td data-label="Fee">{{ $activity->charge_fee ? scout_money($activity->fee_amount) : '—' }}</td>
                    <td data-label="Certificate">{{ $activity->certificateTemplate?->name ?? '—' }}</td>
                    <td data-label="Marked">{{ $activity->attendance_records_count }}</td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('attendance.mark', $activity) }}" class="link">Mark</a>
                        @can('update', $activity)<a href="{{ route('activities.edit', $activity) }}" class="link ml-2">Edit</a>@endcan
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif

    @can('create', \App\Models\Activity::class)
        <x-modal name="create-activity" title="Create activity" maxWidth="2xl" :show="$errors->any()">
            <form method="POST" action="{{ route('activities.store') }}" class="space-y-4">
                @csrf
                @include('activities.partials.form')
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'create-activity')">Cancel</button>
                    <button class="btn-primary">Create</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
