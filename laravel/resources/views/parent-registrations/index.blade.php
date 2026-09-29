<x-app-layout title="Parent registrations">
    <x-page-header title="Parent registrations" description="Parents waiting for verification, with the children they asked for."/>

    @if ($parents->isEmpty())
        <x-empty message="No parent registrations are waiting."/>
    @else
        <x-table :headers="['Parent', 'National ID', 'Email', 'Requested children', 'Registered', '']">
            @foreach ($parents as $parent)
                <tr>
                    <td data-label="Parent" class="font-medium">{{ $parent->name }}</td>
                    <td data-label="National ID">{{ $parent->national_id }}</td>
                    <td data-label="Email">{{ $parent->email }}</td>
                    <td data-label="Requested children">
                        @foreach ($parent->parentLinks as $link)
                            <div>{{ $link->student?->name }} <span class="text-xs text-gray-400">{{ $link->student?->national_id }}</span> <x-badge :value="$link->status"/></div>
                        @endforeach
                    </td>
                    <td data-label="Registered">{{ scout_datetime($parent->created_at) }}</td>
                    <td class="whitespace-nowrap text-right">
                        <form method="POST" action="{{ route('parent-registrations.verify', $parent) }}" class="inline">@csrf<button class="btn-accent btn-sm">Verify</button></form>
                        <x-confirm :action="route('parent-registrations.reject', $parent)" label="Decline" message="Decline {{ $parent->name }}? Their requested links are rejected." confirm="Decline"/>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $parents->links() }}</div>
    @endif
</x-app-layout>
