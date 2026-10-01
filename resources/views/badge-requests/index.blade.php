<x-app-layout title="Badge requests">
    <x-page-header title="Badge requests" description="Requests for proficiency and other badges.">
        <x-slot:actions><a href="{{ route('badge-requests.create') }}" class="btn-primary">Request a badge</a></x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Scout, badge or request id"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\BadgeRequestStatus::options()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($requests->isEmpty())
        <x-empty message="No badge requests yet."/>
    @else
        <x-table :headers="['Request', 'Scout', 'Badge', 'Status', 'Certificate', 'Requested', '']">
            @foreach ($requests as $item)
                <tr>
                    <td data-label="Request" class="font-mono text-xs">{{ $item->request_id }}</td>
                    <td data-label="Scout">{{ $item->student_name }}</td>
                    <td data-label="Badge">{{ $item->badge_name }}</td>
                    <td data-label="Status"><x-badge :value="$item->status"/></td>
                    <td data-label="Certificate" class="font-mono text-xs">{{ $item->certificate_number ?: '—' }}</td>
                    <td data-label="Requested">{{ scout_date($item->created_at) }}</td>
                    <td class="text-right"><a href="{{ route('badge-requests.show', $item) }}" class="link">Open</a></td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $requests->links() }}</div>
    @endif
</x-app-layout>
