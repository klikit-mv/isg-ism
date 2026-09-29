<x-form.input name="name" label="Item name" :value="$item->name" required maxlength="255" placeholder="Event T-shirt" :id="$prefix.'-name'"/>
<x-form.input name="description" label="Description" :value="$item->description" maxlength="1000" :id="$prefix.'-description'"/>
<div class="grid gap-4 sm:grid-cols-3">
    <x-form.input name="price" :label="'Price ('.config('scout.currency').')'" type="number" step="0.01" min="0" :value="$item->price ?? '0.00'" required :id="$prefix.'-price'"/>
    <x-form.input name="stock" label="Stock" type="number" min="0" :value="$item->stock" help="Empty = unlimited" :id="$prefix.'-stock'"/>
    <x-form.input name="max_per_registration" label="Max per scout" type="number" min="1" max="100" :value="$item->max_per_registration" required :id="$prefix.'-max'"/>
</div>
<div>
    <label for="{{ $prefix }}-sizes" class="label">Sizes and measurements</label>
    <textarea id="{{ $prefix }}-sizes" name="sizes" rows="4" class="input font-mono text-sm" placeholder="S: Chest 36 in, Length 26 in&#10;M: Chest 38 in, Length 27 in&#10;L: Chest 40 in, Length 28 in">{{ old('sizes', $item->sizesText()) }}</textarea>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">One size per line, with its measurements after a colon. Or just “S, M, L” without measurements. Leave empty if the item has no sizes.</p>
    @error('sizes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
<x-form.input name="size_guide" label="Size guide note" :value="$item->size_guide" maxlength="1000" placeholder="Measured flat across the chest. If between sizes, choose the larger." :id="$prefix.'-size-guide'"/>
<input type="hidden" name="active" value="0">
<x-form.checkbox name="active" label="Available to order" :checked="$item->active ?? true"/>
