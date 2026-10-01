<x-app-layout :title="$title">
    <x-page-header :title="$title" description="Choose an activity. New activities are created on the Activities page."/>

    <x-filters>
        <x-form.input name="q" label="Activity" :value="request('q')"/>
    </x-filters>

    @if ($activities->isEmpty())
        <x-empty message="No activities are available to you."/>
    @else
        <x-table :headers="['Date', 'Activity', 'Roster', 'Marked', '']">
            @foreach ($activities as $activity)
                <tr>
                    <td data-label="Date">{{ scout_date($activity->date) }}</td>
                    <td data-label="Activity" class="font-medium">{{ $activity->name }}</td>
                    <td data-label="Roster" class="text-xs">{{ $activity->targetSummary() }}</td>
                    <td data-label="Marked">{{ $activity->attendance_records_count }}</td>
                    <td class="text-right"><a href="{{ route($markRoute, $activity) }}" class="btn-primary btn-sm">Open register</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif
</x-app-layout>
