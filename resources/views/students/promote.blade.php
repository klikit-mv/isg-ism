<x-app-layout title="Section promotion">
    <x-page-header title="Section promotion" description="Graduate scouts one section forward. Issued certificates are not changed."/>

    <x-filters>
        <x-form.select name="from" label="From section" :options="collect(\App\Enums\ScoutSection::cases())->filter->canGraduate()->mapWithKeys(fn ($s) => [$s->value => $s->value.' → '.$s->next()->value])->all()" :value="$from"/>
        <x-form.input name="q" label="Name" :value="request('q')"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\StudentStatus::options()" :value="request('status')" placeholder="Any status"/>
        <x-form.select name="group" label="Group" :options="$groups" :value="request('group')" placeholder="Any group"/>
    </x-filters>

    @if ($to === null)
        <x-empty message="Rovers are the last section."/>
    @elseif ($candidates->isEmpty())
        <x-empty message="No {{ $from->value }} scouts match these filters."/>
    @else
        <form method="POST" action="{{ route('promotion.store') }}" x-data="{ all: false }">
            @csrf
            <input type="hidden" name="from" value="{{ $from->value }}">
            <input type="hidden" name="to" value="{{ $to->value }}">
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="rounded border-gray-300 text-navy-600" x-model="all" x-on:change="$root.querySelectorAll('input[name=\'students[]\']').forEach(c => c.checked = all)"> Select all ({{ $candidates->count() }})</label>
                <button type="submit" class="btn-primary btn-sm">Promote selected to {{ $to->value }}</button>
            </div>
            <x-table :headers="['', 'Scout', 'Index', 'Groups', 'Status']">
                @foreach ($candidates as $student)
                    <tr>
                        <td><input type="checkbox" name="students[]" value="{{ $student->id }}" class="rounded border-gray-300 text-navy-600" aria-label="Select {{ $student->name }}"></td>
                        <td data-label="Scout" class="font-medium">{{ $student->name }}</td>
                        <td data-label="Index">{{ $student->index_number }}</td>
                        <td data-label="Groups">{{ implode(', ', \App\Http\Controllers\StudentController::groupNames($student)) ?: '—' }}</td>
                        <td data-label="Status"><x-badge :value="$student->status"/></td>
                    </tr>
                @endforeach
            </x-table>
        </form>
    @endif
</x-app-layout>
