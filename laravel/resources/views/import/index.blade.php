<x-app-layout title="Import">
    <x-page-header title="Legacy workbook import" description="Upload the old Attendance or Finance workbook. It is checked with a dry run first; nothing is written until you confirm.">
        <x-slot:actions>
            <a href="{{ route('import.template') }}" class="btn-secondary">Download sample workbook</a>
            @if ($hasErrors)<a href="{{ route('import.errors') }}" class="btn-secondary">Download messages (CSV)</a>@endif
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('import.preview') }}" enctype="multipart/form-data" class="card mb-6 max-w-xl space-y-3">
        @csrf
        <x-form.file-drop name="file" label="Workbook" accept=".xlsx,.xls,.csv" help="XLSX, XLS or CSV up to 20 MB."/>
        <button class="btn-primary">Inspect</button>
    </form>

    @if ($inspection)
        <h2 class="mb-2 font-semibold">Sheets found</h2>
        @if ($inspection['missing_identity'])
            <div class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                Missing identity sheets: {{ implode(', ', $inspection['missing_identity']) }}. Rows that refer to unknown scouts or users will fail.
            </div>
        @endif
        <div class="mb-6">
            <x-table :headers="['Sheet', 'Rows', 'Valid', 'Invalid', 'Missing columns']">
                @foreach ($inspection['sheets'] as $sheet)
                    <tr>
                        <td data-label="Sheet" class="font-medium">{{ $sheet['name'] }} @unless ($sheet['known'])<x-badge value="Not imported"/>@endunless</td>
                        <td data-label="Rows">{{ $sheet['rows'] }}</td>
                        <td data-label="Valid">{{ $sheet['valid'] }}</td>
                        <td data-label="Invalid">{{ $sheet['invalid'] }}</td>
                        <td data-label="Missing columns" class="text-xs">{{ implode(', ', $sheet['missing']) ?: '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    @endif

    @if ($result)
        <h2 class="mb-2 font-semibold">{{ $result['dry_run'] ? 'Dry run result (nothing saved)' : 'Import result' }}</h2>
        <div class="mb-4">
            <x-table :headers="['Sheet', 'Imported', 'Errors']">
                @foreach ($result['counts'] as $sheet => $count)
                    <tr>
                        <td data-label="Sheet">{{ $sheet }}</td>
                        <td data-label="Imported">{{ $count['imported'] }}</td>
                        <td data-label="Errors">{{ $count['errors'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </div>
        @if ($result['errors'])
            <details class="card mb-4 !p-4">
                <summary class="cursor-pointer text-sm font-medium">{{ count($result['errors']) }} message(s)</summary>
                <ul class="mt-2 space-y-1 text-xs">
                    @foreach (array_slice($result['errors'], 0, 50) as $error)
                        <li><span class="font-mono">{{ $error['sheet'] }}:{{ $error['row'] }}</span> — {{ $error['message'] }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
        @if ($result['dry_run'])
            <x-confirm :action="route('import.confirm')" label="Import for real" variant="primary" size="md" title="Import workbook" message="Write these rows to the database? Existing records are updated by National ID, legacy id or year." confirm="Import"/>
        @endif
    @endif
</x-app-layout>
