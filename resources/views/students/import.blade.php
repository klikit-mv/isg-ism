<x-app-layout title="Import scouts">
    <x-page-header title="Import scouts from Excel" description="Upload, check the preview, then confirm. Existing National IDs are skipped.">
        <x-slot:actions>
            <a href="{{ route('students.index') }}" class="btn-secondary">Back to students</a>
            <a href="{{ route('students.import.template') }}" class="btn-secondary">Download template</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('students.import.preview') }}" enctype="multipart/form-data" class="card mb-6 max-w-xl space-y-3">
        @csrf
        <x-form.file-drop name="file" label="Spreadsheet" accept=".xlsx,.xls,.csv" help="XLSX, XLS or CSV up to 10 MB. The header row must include name, national_id and email."/>
        <button class="btn-primary">Preview</button>
    </form>

    @if ($report)
        @if ($report['missing'])
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200">
                The header row is missing: {{ implode(', ', $report['missing']) }}.
            </div>
        @else
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-badge tone="green" :value="collect($report['rows'])->whereIn('result', ['ready', 'created'])->count().' ready'"/>
                <x-badge tone="amber" :value="$report['skipped'].' already exist'"/>
                <x-badge tone="red" :value="$report['errors'].' with errors'"/>
                @if ($report['preview'] && collect($report['rows'])->where('result', 'ready')->isNotEmpty())
                    <x-confirm :action="route('students.import.confirm')" label="Import ready rows" variant="primary" size="md" title="Import scouts" message="Enrol every row marked ready? Each gets a sign-in account." confirm="Import"/>
                @endif
            </div>
            <x-table :headers="['Row', 'Name', 'National ID', 'Result', 'Message']">
                @foreach ($report['rows'] as $row)
                    <tr>
                        <td data-label="Row">{{ $row['row'] }}</td>
                        <td data-label="Name">{{ $row['name'] }}</td>
                        <td data-label="National ID">{{ $row['national_id'] }}</td>
                        <td data-label="Result"><x-badge :value="ucfirst($row['result'])" :tone="['ready' => 'green', 'created' => 'green', 'exists' => 'amber', 'error' => 'red'][$row['result']] ?? 'gray'"/></td>
                        <td data-label="Message" class="text-xs">{{ $row['message'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    @endif
</x-app-layout>
