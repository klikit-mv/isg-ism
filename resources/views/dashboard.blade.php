<x-app-layout title="Modules">
    <x-page-header :title="'Welcome, '.auth()->user()->name" description="Choose where you want to work."/>


    @if ($modules === [])
        <x-empty message="Your account does not have any modules yet. Please contact an administrator."/>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($modules as $module)
                <a href="{{ route('modules.enter', $module['key']) }}" class="group flex items-start gap-4 rounded-2xl border p-5 text-white shadow-md transition hover:-translate-y-0.5 hover:shadow-lg {{ \App\Support\Tones::for($module['key']) }}" data-module="{{ $module['key'] }}">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/20 text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="{{ \App\Support\Icons::path($module['icon']) }}"/></svg>
                    </span>
                    <span>
                        <span class="block text-lg font-semibold">{{ $module['title'] }}</span>
                        <span class="mt-1 block text-sm text-white/85">{{ $module['description'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</x-app-layout>
