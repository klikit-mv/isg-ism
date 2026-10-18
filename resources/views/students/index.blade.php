<x-app-layout title="Students">
    <x-page-header title="Students" :description="$pendingCount ? $pendingCount.' registration'.($pendingCount === 1 ? '' : 's').' waiting for verification.' : 'The scout registry.'">
        <x-slot:actions>
            @if (Route::has('students.import') && auth()->user()->can('import', \App\Models\Student::class))
                <a href="{{ route('students.import') }}" class="btn-secondary">Import from Excel</a>
            @endif
            @can('create', \App\Models\Student::class)
                <a href="{{ route('students.create') }}" class="btn-primary">Enrol scout</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Name, National ID or index"/>
        <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="Any section"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\StudentStatus::options()" :value="request('status')" placeholder="Any status"/>
    </x-filters>

    @if ($students->isEmpty())
        <x-empty message="No scouts match these filters."/>
    @else
        <x-table :headers="[['label' => 'Scout', 'sort' => 'name'], ['label' => 'Index', 'sort' => 'index'], ['label' => 'National ID', 'sort' => 'national_id'], ['label' => 'Section', 'sort' => 'section'], ['label' => 'Status', 'sort' => 'status'], '']">
            @foreach ($students as $student)
                <tr>
                    <td data-label="Scout">
                        <a href="{{ route('students.show', $student) }}" class="flex items-center gap-3 font-medium hover:underline">
                            @include('students.partials.avatar', ['student' => $student, 'size' => 'h-8 w-8'])
                            {{ $student->name }}
                        </a>
                    </td>
                    <td data-label="Index">{{ $student->index_number }}</td>
                    <td data-label="National ID">{{ $student->national_id }}</td>
                    <td data-label="Section"><x-badge :value="$student->section"/></td>
                    <td data-label="Status"><x-badge :value="$student->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        @if ($student->status === \App\Enums\StudentStatus::Pending && auth()->user()->can('verify', $student))
                            <form method="POST" action="{{ route('students.verify', $student) }}" class="inline">@csrf<button class="btn-accent btn-sm">Verify</button></form>
                        @endif
                        <a href="{{ route('students.show', $student) }}" class="link ml-2">Open</a>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $students->links() }}</div>
    @endif
</x-app-layout>
