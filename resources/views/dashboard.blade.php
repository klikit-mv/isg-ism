<x-app-layout title="Modules">
    <x-page-header :title="'Welcome, '.auth()->user()->name" description="Choose where you want to work."/>


    @if ($modules === [])
        <x-empty message="Your account does not have any modules yet. Please contact an administrator."/>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modules as $module)
                <div class="card flex flex-col gap-4 transition hover:border-navy-300 hover:shadow-md" data-module="{{ $module['key'] }}">
                    <a href="{{ route('modules.enter', $module['key']) }}" class="group flex items-start gap-4">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-navy-100 text-navy-700 group-hover:bg-navy-700 group-hover:text-white dark:bg-navy-900/60 dark:text-navy-200">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($module['icon']) }}"/></svg>
                        </span>
                        <span>
                            <span class="block font-semibold text-gray-900 dark:text-gray-100">{{ $module['title'] }}</span>
                            <span class="mt-1 block text-sm text-gray-500 dark:text-gray-400">{{ $module['description'] }}</span>
                        </span>
                    </a>
                    @php($pages = \App\Support\Navigation::for(auth()->user(), $module['key']))
                    @if ($pages !== [])
                        <ul class="flex flex-wrap gap-2 border-t border-gray-100 pt-3 dark:border-gray-700" data-testid="module-pages">
                            @foreach ($pages as $page)
                                <li><a href="{{ $page['url'] }}" class="inline-block rounded-lg bg-navy-50 px-2.5 py-1 text-xs font-medium text-navy-800 hover:bg-navy-700 hover:text-white dark:bg-navy-900/50 dark:text-navy-100 dark:hover:bg-navy-600">{{ $page['label'] }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-app-layout>
