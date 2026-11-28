<x-app-layout title="Reports">
    <x-page-header title="Reports" description="On-screen, printable and exportable to XLSX or CSV. Leaders see their own scouts."/>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($catalog as $type => $report)
            <a href="{{ route('reports.show', $type) }}" class="card block hover:border-navy-300">
                <h2 class="font-semibold">{{ $report['title'] }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $report['description'] }}</p>
            </a>
        @endforeach
    </div>
</x-app-layout>
