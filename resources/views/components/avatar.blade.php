@props(['user'])
@php $url = $user->avatarUrl(); @endphp
@if ($url)
    <img src="{{ $url }}" alt="" {{ $attributes->merge(['class' => 'shrink-0 rounded-full object-cover']) }} data-testid="user-avatar">
@else
    <span {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center rounded-full bg-gold-500 font-bold text-white']) }}>{{ $user->initials() }}</span>
@endif
