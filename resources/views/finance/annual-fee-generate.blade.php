<x-app-layout :title="'Generate '.$year->year.' invoices'">
    <x-page-header :title="'Generate '.$year->year.' invoices'" :description="scout_money($year->amount).' each. People already invoiced are skipped.'">
        <x-slot:actions>
            <a href="{{ route('annual-fees.years') }}" class="btn-secondary">Back</a>
            <a href="{{ route('annual-fees.generate', ['year' => $year, 'inactive' => request()->boolean('inactive') ? 0 : 1]) }}" class="btn-secondary">{{ request()->boolean('inactive') ? 'Hide inactive' : 'Include inactive' }}</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('annual-fees.import-preview', $year) }}" enctype="multipart/form-data" class="card mb-4 space-y-3 !p-4">
        @csrf
        <div>
            <h2 class="font-semibold">Bulk generate from an Excel list</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Upload your list (.xlsx, .xls or .csv) with a heading row and a <strong>Name</strong> column. A <strong>National ID</strong> column (more exact) and a <strong>Section</strong> column are optional. You see a preview before anything is created.</p>
        </div>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1"><x-form.input name="file" label="List file" type="file" accept=".xlsx,.xls,.csv,.txt" required/></div>
            <label class="inline-flex items-center gap-2 pb-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" name="inactive" value="1" class="rounded border-gray-300 text-navy-600" @checked(request()->boolean('inactive'))> Include inactive scouts</label>
            <button class="btn-primary">Preview</button>
        </div>
    </form>

    <form method="POST" action="{{ route('annual-fees.generate.store', $year) }}"
          x-data="{ search: '', section: '', matches(row) { return (! this.search || row.dataset.name.includes(this.search.toLowerCase())) && (! this.section || row.dataset.section === this.section); } }">
        @csrf
        <div class="card mb-4 grid gap-3 !p-4 sm:grid-cols-4">
            <input type="search" x-model="search" class="input" placeholder="Search name">
            <select x-model="section" class="input">
                <option value="">Any section</option>
                @foreach (\App\Enums\ScoutSection::cases() as $s)<option value="{{ $s->value }}">{{ $s->value }}</option>@endforeach
            </select>
            <div class="flex items-center gap-2 sm:col-span-4">
                <label class="text-sm text-gray-600 dark:text-gray-300" for="bulk-section">Set the section for everyone shown to</label>
                <select id="bulk-section" class="input !w-auto !py-1 text-sm" x-ref="bulk">
                    @foreach (\App\Enums\ScoutSection::cases() as $s)<option value="{{ $s->value }}">{{ $s->value }}</option>@endforeach
                </select>
                <button type="button" class="btn-secondary btn-sm" x-on:click="$root.querySelectorAll('tbody tr').forEach(r => { if (r.style.display !== 'none') { const sel = r.querySelector('select'); if (sel) sel.value = $refs.bulk.value; } })">Apply</button>
            </div>
            <div class="flex gap-2 sm:col-span-4">
                <button type="button" class="btn-secondary btn-sm" x-on:click="$root.querySelectorAll('input[name=\'people[]\']').forEach(c => c.checked = true)">Select all</button>
                <button type="button" class="btn-secondary btn-sm" x-on:click="$root.querySelectorAll('input[name=\'people[]\']').forEach(c => c.checked = false)">Deselect all</button>
                <button type="submit" class="btn-primary btn-sm ml-auto">Generate</button>
            </div>
        </div>

        <x-table :headers="['', 'Name', 'Type', 'Section for '.$year->year, 'Status']">
            @foreach ($people as $person)
                <tr x-show="matches($el)" data-name="{{ strtolower($person['name']) }}" data-section="{{ $person['section'] }}">
                    <td><input type="checkbox" name="people[]" value="{{ $person['key'] }}" @checked(! $person['invoiced']) class="rounded border-gray-300 text-navy-600" aria-label="Select {{ $person['name'] }}"></td>
                    <td data-label="Name" class="font-medium">{{ $person['name'] }} <span class="block text-xs text-gray-400">{{ $person['national_id'] }}</span></td>
                    <td data-label="Type">{{ $person['type']->label() }}</td>
                    <td data-label="Section">
                        @if ($person['type'] === \App\Enums\PersonType::Student)
                            <select name="sections[{{ $person['key'] }}]" class="input !w-auto !py-1 text-xs">
                                @foreach (\App\Enums\ScoutSection::cases() as $s)<option value="{{ $s->value }}" @selected($s->value === $person['section'])>{{ $s->value }}</option>@endforeach
                            </select>
                        @else
                            —
                        @endif
                    </td>
                    <td data-label="Status">@if ($person['invoiced'])<x-badge tone="green" value="Already invoiced"/>@else<x-badge value="Not invoiced"/>@endif</td>
                </tr>
            @endforeach
        </x-table>
    </form>
</x-app-layout>
