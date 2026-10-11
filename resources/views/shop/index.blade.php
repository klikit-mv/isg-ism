<x-app-layout title="Scout shop">
    <x-page-header title="Scout shop" :description="$shopEnabled ? 'Order for a scout, then pay to confirm.' : 'The shop is closed at the moment.'">
        <x-slot:actions>
            @if ($manager)
                <button type="button" class="btn-primary" x-data x-on:click="$dispatch('open-modal', 'add-item')">Add item</button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-filters>
        <x-form.input name="q" label="Search" :value="request('q')" placeholder="Name or description"/>
        @if ($manager)
            <x-form.select name="status" label="Status" :options="\App\Enums\ShopItemStatus::options()" :value="request('status')" placeholder="Any status"/>
        @endif
    </x-filters>

    @if ($items->isEmpty())
        <x-empty message="No items in the shop yet."/>
    @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($items as $item)
                <div class="card flex flex-col !p-0 overflow-hidden" data-item="{{ $item->uuid }}">
                    @if ($url = photo_url($item->image_path))
                        <img src="{{ $url }}" alt="{{ $item->name }}" class="h-40 w-full object-cover">
                    @else
                        <div class="flex h-40 items-center justify-center bg-navy-50 text-navy-300 dark:bg-navy-950/60" data-testid="placeholder">
                            <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z"/></svg>
                        </div>
                    @endif
                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h2 class="font-semibold">{{ $item->name }}</h2>
                            @if ($manager)<x-badge :value="$item->status"/>@endif
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item->description }}</p>
                        <div class="mt-auto flex items-center justify-between">
                            <span class="font-bold text-navy-700 dark:text-navy-200">{{ scout_money($item->price) }}</span>
                            <span class="text-xs {{ $item->stock_qty > 0 ? 'text-gray-500' : 'text-rose-600' }}">{{ $item->stock_qty > 0 ? $item->stock_qty.' in stock' : 'Out of stock' }}</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @if ($shopEnabled && $item->isActive() && $item->stock_qty > 0 && $students)
                                <button type="button" class="btn-accent btn-sm" x-data x-on:click="$dispatch('open-modal', 'buy-{{ $item->uuid }}')">Buy</button>
                            @endif
                            @if ($manager)
                                <button type="button" class="btn-secondary btn-sm" x-data x-on:click="$dispatch('open-modal', 'edit-{{ $item->uuid }}')">Edit</button>
                                <x-confirm :action="route('shop.destroy', $item)" method="DELETE" label="Delete" message="Remove {{ $item->name }} from the shop? Past purchases keep their record." confirm="Delete"/>
                            @endif
                        </div>
                    </div>
                </div>

                <x-modal :name="'buy-'.$item->uuid" :title="'Buy '.$item->name" maxWidth="md">
                    <form method="POST" action="{{ route('shop.buy', $item) }}" class="space-y-4">
                        @csrf
                        <x-form.select name="student" label="For scout" :options="$students" :value="count($students) === 1 ? array_key_first($students) : null" placeholder="Choose a scout" required :id="'student-'.$item->uuid"/>
                        <x-form.input name="quantity" label="Quantity" type="number" min="1" :max="$item->stock_qty" value="1" required :id="'qty-'.$item->uuid"/>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'buy-{{ $item->uuid }}')">Cancel</button>
                            <button class="btn-primary">Place order</button>
                        </div>
                    </form>
                </x-modal>

                @if ($manager)
                    <x-modal :name="'edit-'.$item->uuid" :title="'Edit '.$item->name" maxWidth="lg">
                        <form method="POST" action="{{ route('shop.update', $item) }}" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            @method('PUT')
                            @include('shop.partials.item-fields', ['item' => $item])
                            <div class="flex justify-end gap-2">
                                <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'edit-{{ $item->uuid }}')">Cancel</button>
                                <button class="btn-primary">Save</button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            @endforeach
        </div>
        <div class="mt-4">{{ $items->links() }}</div>
    @endif

    @if ($manager)
        <x-modal name="add-item" title="Add shop item" maxWidth="lg">
            <form method="POST" action="{{ route('shop.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @include('shop.partials.item-fields', ['item' => new \App\Models\ShopItem])
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn-secondary" x-on:click="$dispatch('close-modal', 'add-item')">Cancel</button>
                    <button class="btn-primary">Add item</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
