<x-app-layout title="Certificates">
    <x-page-header title="Certificates" description="Badge, general and leadership certificates.">
        <x-slot:actions>
            @can('create', \App\Models\Certificate::class)
                <a href="{{ route('certificates.create') }}" class="btn-primary">Issue certificate</a>
                <a href="{{ route('certificates.bulk-create') }}" class="btn-secondary">Bulk issue</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
        <x-stat label="Total" :value="$stats['total']"/>
        <x-stat label="This year" :value="$stats['this_year']"/>
        <x-stat label="Badge" :value="$stats['badge']"/>
        <x-stat label="General" :value="$stats['general']"/>
        <x-stat label="Leadership" :value="$stats['leadership']"/>
        <x-stat label="Requests pending" :value="$stats['requests_pending']" tone="gold"/>
        <x-stat label="Requests approved" :value="$stats['requests_approved']"/>
        <x-stat label="Generated today" :value="$stats['generated_today']"/>
    </div>

    @if ($activitySummaries->isNotEmpty())
        <details class="card mb-4 !p-4" @if ($activitySummaries->where('missing', '>', 0)->isNotEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold">Activity certificates</summary>
            <div class="mt-3">
                <x-table :headers="['Activity', 'Template', 'Present/Late', 'Issued', 'Missing', '']">
                    @foreach ($activitySummaries as $summary)
                        <tr>
                            <td data-label="Activity">{{ $summary['activity']->name }} <span class="block text-xs text-gray-400">{{ scout_date($summary['activity']->date) }}</span></td>
                            <td data-label="Template">{{ $summary['activity']->certificateTemplate?->name }}</td>
                            <td data-label="Present/Late">{{ $summary['present'] }}</td>
                            <td data-label="Issued">{{ $summary['issued'] }}</td>
                            <td data-label="Missing">{{ $summary['missing'] }}</td>
                            <td class="text-right">
                                @if ($summary['missing'] > 0)
                                    <form method="POST" action="{{ route('certificates.activities.issue', $summary['activity']) }}">@csrf<button class="btn-accent btn-sm">Issue</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>
        </details>
    @endif

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Number, name or title"/>
        @if (count($students) > 1)
            <x-form.select name="student" label="Scout" :options="$students" :value="request('student')" placeholder="Any scout"/>
        @endif
        <x-form.select name="type" label="Type" :options="\App\Enums\CertificateType::options()" :value="request('type')" placeholder="Any type"/>
        <x-form.select name="status" label="Status" :options="\App\Enums\CertificateStatus::options()" :value="request('status')" placeholder="Any status"/>
        <x-form.select name="badge" label="Badge" :options="$badges" :value="request('badge')" placeholder="Any badge"/>
        <x-form.input name="from" label="From" type="date" :value="request('from')"/>
        <x-form.input name="to" label="To" type="date" :value="request('to')"/>
        <x-form.select name="sort" label="Order" :options="['newest' => 'Newest first', 'oldest' => 'Oldest first']" :value="request('sort', 'newest')"/>
    </x-filters>

    @if ($certificates->isEmpty())
        <x-empty message="No certificates match these filters."/>
    @else
        @can('bulkDownload', \App\Models\Certificate::class)
            <form id="bulk-download" method="POST" action="{{ route('certificates.bulk-download') }}">@csrf</form>
            <div class="mb-2 flex justify-end"><button form="bulk-download" class="btn-secondary btn-sm">Download selected as ZIP</button></div>
        @endcan
        <x-table :headers="['', ['label' => 'Number', 'sort' => 'number'], ['label' => 'Scout', 'sort' => 'scout'], ['label' => 'Certificate', 'sort' => 'title'], ['label' => 'Type', 'sort' => 'type'], ['label' => 'Awarded', 'sort' => 'awarded'], ['label' => 'Status', 'sort' => 'status'], '']" default-sort="awarded:desc">
            @foreach ($certificates as $certificate)
                <tr>
                    <td>@can('bulkDownload', \App\Models\Certificate::class)<input form="bulk-download" type="checkbox" name="certificates[]" value="{{ $certificate->uuid }}" class="rounded border-gray-300 text-navy-600" aria-label="Select {{ $certificate->cert_number }}">@endcan</td>
                    <td data-label="Number" class="font-mono text-xs">{{ $certificate->cert_number }}</td>
                    <td data-label="Scout">{{ $certificate->student_name }}</td>
                    <td data-label="Certificate">{{ $certificate->displayTitle() }}</td>
                    <td data-label="Type"><x-badge :value="$certificate->type"/></td>
                    <td data-label="Awarded">{{ scout_date($certificate->date_awarded) }}</td>
                    <td data-label="Status"><x-badge :value="$certificate->status"/></td>
                    <td class="whitespace-nowrap text-right">
                        <a href="{{ route('certificates.show', $certificate) }}" class="link">View</a>
                        <a href="{{ route('certificates.download', $certificate) }}" class="link ml-2">PDF</a>
                    </td>
                </tr>
            @endforeach
        </x-table>
        <div class="mt-4">{{ $certificates->links() }}</div>
    @endif
</x-app-layout>
