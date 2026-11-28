<x-app-layout title="Parent registrations">
    <x-page-header title="Parent registrations" description="Parents waiting for verification, and parents already verified."/>

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700" aria-label="Parent registration tabs" data-testid="parent-tabs">
        @foreach (['pending' => ['Pending verification', $pendingCount], 'verified' => ['Verified', $verifiedCount]] as $key => [$label, $count])
            <a href="{{ route('parent-registrations.index', ['tab' => $key]) }}" @class([
                '-mb-px whitespace-nowrap border-b-2 px-4 py-2 text-sm font-medium',
                'border-navy-600 text-navy-700 dark:border-navy-400 dark:text-navy-200' => $tab === $key,
                'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400' => $tab !== $key,
            ])>{{ $label }} <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs dark:bg-gray-800">{{ $count }}</span></a>
        @endforeach
    </nav>

    @if ($parents->isEmpty())
        <x-empty :message="$tab === 'verified' ? 'No parents have been verified yet.' : 'No parent registrations are waiting.'"/>
    @else
        <x-table :headers="$tab === 'verified' ? ['Parent', 'National ID', 'Email', 'Children', 'Verified'] : ['Parent', 'National ID', 'Email', 'Requested children', 'Registered', '']">
            @foreach ($parents as $parent)
                <tr>
                    <td data-label="Parent" class="font-medium">{{ $parent->name }}</td>
                    <td data-label="National ID">{{ $parent->national_id }}</td>
                    <td data-label="Email">{{ $parent->email }}</td>
                    <td data-label="{{ $tab === 'verified' ? 'Children' : 'Requested children' }}">
                        @foreach ($parent->parentLinks as $link)
                            <div>{{ $link->student?->name }} <span class="text-xs text-gray-400">{{ $link->student?->national_id }}</span> <x-badge :value="$link->status"/></div>
                        @endforeach
                    </td>
                    @if ($tab === 'verified')
                        <td data-label="Verified">{{ scout_datetime($parent->verified_at) }}@if ($parent->verifier)<span class="block text-xs text-gray-400">by {{ $parent->verifier->name }}</span>@endif</td>
                    @else
                        <td data-label="Registered">{{ scout_datetime($parent->created_at) }}</td>
                        <td class="whitespace-nowrap text-right">
                            <form method="POST" action="{{ route('parent-registrations.verify', $parent) }}" class="inline">@csrf<button class="btn-accent btn-sm">Verify</button></form>
                            <x-confirm :action="route('parent-registrations.reject', $parent)" label="Decline" message="Decline {{ $parent->name }}? Their requested links are rejected." confirm="Decline"/>
                        </td>
                    @endif
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $parents->links() }}</div>
    @endif
</x-app-layout>
