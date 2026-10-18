@php $url = photo_url($student->photo_path); @endphp
@if ($url)
    <img src="{{ $url }}" alt="" class="{{ $size ?? 'h-10 w-10' }} shrink-0 rounded-full object-cover">
@else
    <span class="{{ $size ?? 'h-10 w-10' }} flex shrink-0 items-center justify-center rounded-full bg-navy-100 text-xs font-bold text-navy-700 dark:bg-navy-900 dark:text-navy-200">{{ \Illuminate\Support\Str::of($student->name)->explode(' ')->take(2)->map(fn ($p) => \Illuminate\Support\Str::substr($p, 0, 1))->implode('') }}</span>
@endif
