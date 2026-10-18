@php $suffix = $item->uuid ?? 'new'; @endphp
<x-form.input name="name" label="Name" :value="$item->name" required :id="'name-'.$suffix"/>
<x-form.textarea name="description" label="Description" :value="$item->description"/>
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="price" label="Price" type="number" step="0.01" min="0" :value="$item->price" required :id="'price-'.$suffix"/>
    <x-form.input name="stock_qty" label="Stock" type="number" min="0" :value="$item->stock_qty ?? 0" required :id="'stock-'.$suffix"/>
</div>
@if ($item->exists)
    <x-form.select name="status" label="Status" :options="\App\Enums\ShopItemStatus::options()" :value="$item->status" :id="'status-'.$suffix"/>
@endif
<x-form.file-drop name="image" label="Image (optional)" accept="image/png,image/jpeg,image/webp" help="PNG, JPEG or WebP, up to 5 MB."/>
