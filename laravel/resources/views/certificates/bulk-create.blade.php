<x-app-layout title="Bulk issue">
    <x-page-header title="Bulk issue general certificates" description="Each scout is issued separately; failures are listed and never undo the others.">
        <x-slot:actions><a href="{{ route('certificates.index') }}" class="btn-secondary">Back</a></x-slot:actions>
    </x-page-header>

    <form method="GET" class="card mb-4 flex flex-wrap items-end gap-3 !p-4">
        <x-form.select name="section" label="Filter by section" :options="\App\Enums\ScoutSection::options()" :value="request('section')" placeholder="All sections"/>
        <button class="btn-secondary btn-sm">Filter</button>
    </form>

    <form method="POST" action="{{ route('certificates.bulk-store') }}" class="space-y-4">
        @csrf
        <div class="card grid gap-4 sm:grid-cols-3">
            <x-form.input name="title" label="Title" required/>
            <x-form.input name="date_awarded" label="Date awarded" type="date" :value="now()->toDateString()" required/>
            <x-form.select name="template" label="Template" :options="$templates" placeholder="Choose a template" required/>
        </div>
        @if ($students->isEmpty())
            <x-empty message="No scouts to show."/>
        @else
            <div x-data class="flex gap-2">
                <button type="button" class="btn-secondary btn-sm" x-on:click="document.querySelectorAll('input[name=\'students[]\']').forEach(c => c.checked = true)">Select all</button>
                <button type="button" class="btn-secondary btn-sm" x-on:click="document.querySelectorAll('input[name=\'students[]\']').forEach(c => c.checked = false)">Deselect all</button>
            </div>
            <x-table :headers="['', 'Scout', 'Section']">
                @foreach ($students as $student)
                    <tr>
                        <td><input type="checkbox" name="students[]" value="{{ $student->id }}" class="rounded border-gray-300 text-navy-600" aria-label="Select {{ $student->name }}"></td>
                        <td data-label="Scout">{{ $student->name }}</td>
                        <td data-label="Section"><x-badge :value="$student->section"/></td>
                    </tr>
                @endforeach
            </x-table>
            <button class="btn-primary">Issue to selected scouts</button>
        @endif
    </form>
</x-app-layout>
