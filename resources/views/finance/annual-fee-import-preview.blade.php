<x-app-layout :title="'Preview '.$year->year.' invoices'">
    <x-page-header :title="'Preview: '.$year->year.' invoices from your list'" :description="$fileName.' · '.scout_money($year->amount).' each. Nothing is created until you press Generate.'">
        <x-slot:actions><a href="{{ route('annual-fees.generate', $year) }}" class="btn-secondary">Back</a></x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Rows in the list" :value="$match['rows']"/>
        <x-stat label="Ready to invoice" :value="count($match['matched'])" tone="gold"/>
        <x-stat label="Already invoiced" :value="count($match['invoiced'])"/>
        <x-stat label="Need attention" :value="count($match['problems'])"/>
    </div>
    <p class="mb-4 text-xs text-gray-500">
        Matched by {{ $match['columns']['national_id'] ? 'National ID, then name' : 'name' }}
        (column “{{ $match['columns']['name'] ?? '—' }}”){{ $match['columns']['section'] ? ', section from the “Section” column' : '' }}.
    </p>

    <form method="POST" action="{{ route('annual-fees.generate.store', $year) }}" class="space-y-6">
        @csrf
        @if (count($match['matched']) > 0)
            <section>
                <h2 class="mb-2 font-semibold">Will be invoiced ({{ count($match['matched']) }})</h2>
                <x-table :headers="['', 'Row', 'Name in the list', 'Matched to', 'Type', 'Section for '.$year->year]">
                    @foreach ($match['matched'] as $person)
                        <tr>
                            <td><input type="checkbox" name="people[]" value="{{ $person['key'] }}" checked class="rounded border-gray-300 text-navy-600" aria-label="Include {{ $person['name'] }}"></td>
                            <td data-label="Row" class="text-xs text-gray-500">{{ $person['row'] }}</td>
                            <td data-label="Name in the list">{{ $person['file_name'] }}</td>
                            <td data-label="Matched to" class="font-medium">{{ $person['name'] }} <span class="block text-xs text-gray-400">{{ $person['national_id'] }}</span></td>
                            <td data-label="Type">{{ $person['type']->label() }}</td>
                            <td data-label="Section">
                                @if ($person['type'] === \App\Enums\PersonType::Student)
                                    <select name="sections[{{ $person['key'] }}]" class="input !w-auto !py-1 text-xs">
                                        @foreach (\App\Enums\ScoutSection::cases() as $s)<option value="{{ $s->value }}" @selected($s->value === ($person['file_section'] ?? $person['section']))>{{ $s->value }}</option>@endforeach
                                    </select>
                                @else — @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </section>
            <div class="flex justify-end"><button class="btn-primary">Generate {{ count($match['matched']) }} invoice(s)</button></div>
        @else
            <x-empty message="Nobody in this list can be invoiced."/>
        @endif
    </form>

    @if (count($match['invoiced']) > 0)
        <section class="mt-8">
            <h2 class="mb-2 font-semibold">Already invoiced for {{ $year->year }} ({{ count($match['invoiced']) }})</h2>
            <x-table :headers="['Row', 'Name in the list', 'Matched to']">
                @foreach ($match['invoiced'] as $person)
                    <tr><td data-label="Row" class="text-xs text-gray-500">{{ $person['row'] }}</td><td data-label="Name in the list">{{ $person['file_name'] }}</td><td data-label="Matched to">{{ $person['name'] }}</td></tr>
                @endforeach
            </x-table>
        </section>
    @endif

    @if (count($match['problems']) > 0)
        <section class="mt-8">
            <h2 class="mb-2 font-semibold text-amber-700 dark:text-amber-300">Need attention ({{ count($match['problems']) }})</h2>
            <x-table :headers="['Row', 'Name in the list', 'Problem']">
                @foreach ($match['problems'] as $problem)
                    <tr><td data-label="Row" class="text-xs text-gray-500">{{ $problem['row'] }}</td><td data-label="Name in the list">{{ $problem['name'] }}</td><td data-label="Problem">{{ $problem['reason'] }}</td></tr>
                @endforeach
            </x-table>
        </section>
    @endif
</x-app-layout>
