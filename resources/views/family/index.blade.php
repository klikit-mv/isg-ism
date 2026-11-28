<x-app-layout title="My students">
    <x-page-header title="My students" description="Children linked to your account."/>

    @if ($children->isEmpty())
        <x-empty message="No children are linked to your account yet. Links appear once a leader approves them."/>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($children as $child)
                <a href="{{ route('family.show', $child) }}" class="card flex items-center gap-4 hover:border-navy-300">
                    @include('students.partials.avatar', ['student' => $child, 'size' => 'h-14 w-14'])
                    <div>
                        <div class="font-semibold">{{ $child->name }}</div>
                        <div class="mt-1 flex flex-wrap gap-1"><x-badge :value="$child->section"/><x-badge :value="$child->status"/></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
