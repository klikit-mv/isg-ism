{{-- GET filter form with a reset link. --}}
@props(['action' => null])

<form method="GET" action="{{ $action ?? url()->current() }}" class="card mb-4 !p-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        {{ $slot }}
    </div>
    <div class="mt-3 flex gap-2">
        <button type="submit" class="btn-primary btn-sm">Filter</button>
        <a href="{{ $action ?? url()->current() }}" class="btn-secondary btn-sm">Reset</a>
    </div>
</form>
