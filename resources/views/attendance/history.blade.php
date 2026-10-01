<x-app-layout :title="$title">
    <x-page-header :title="$title"/>

    <x-filters>
        <x-form.input name="q" label="Activity" :value="request('q')"/>
        @if ($children)
            <x-form.select name="child" label="Child" :options="$children" :value="request('child')" placeholder="All children"/>
        @endif
        <x-form.select name="status" label="Status" :options="\App\Enums\AttendanceStatus::options()" :value="request('status')" placeholder="Any status"/>
        <x-form.input name="from" label="From" type="date" :value="request('from')"/>
        <x-form.input name="to" label="To" type="date" :value="request('to')"/>
    </x-filters>

    @if ($records->isEmpty())
        <x-empty message="No attendance records yet."/>
    @else
        <x-table :headers="['Date', 'Activity', 'Scout', 'Status', 'Remarks']">
            @foreach ($records as $record)
                <tr>
                    <td data-label="Date">{{ scout_date($record->activity?->date) }}</td>
                    <td data-label="Activity">{{ $record->activity?->name }}</td>
                    <td data-label="Scout">{{ $record->student?->name }}</td>
                    <td data-label="Status"><x-badge :value="$record->status"/></td>
                    <td data-label="Remarks">{{ $record->remarks ?: '—' }}</td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $records->links() }}</div>
    @endif
</x-app-layout>
