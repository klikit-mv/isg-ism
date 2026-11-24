<x-app-layout :title="$module['title']">
    <x-page-header :title="$module['title']" :description="$module['description']"/>

    <nav aria-label="{{ $module['title'] }} menu" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="menu-cards">
        @foreach ($pages as $page)
            <a href="{{ $page['url'] }}" class="card group flex items-center gap-4 transition hover:border-navy-300 hover:shadow-md">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-navy-100 text-navy-700 group-hover:bg-navy-700 group-hover:text-white dark:bg-navy-900/60 dark:text-navy-200">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($module['icon']) }}"/></svg>
                </span>
                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $page['label'] }}</span>
            </a>
        @endforeach
    </nav>
</x-app-layout>
