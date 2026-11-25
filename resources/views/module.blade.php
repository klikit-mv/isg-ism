<x-app-layout :title="$module['title']">
    <x-page-header :title="$module['title']" :description="$module['description']"/>

    <nav aria-label="{{ $module['title'] }} menu" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" data-testid="menu-cards">
        @foreach ($pages as $page)
            <a href="{{ $page['url'] }}" class="group flex items-center gap-4 rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg {{ \App\Support\Tones::for($module['key']) }}">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/70 dark:bg-white/10">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($module['icon']) }}"/></svg>
                </span>
                <span class="font-semibold">{{ $page['label'] }}</span>
            </a>
        @endforeach
    </nav>
</x-app-layout>
