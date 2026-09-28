<x-form.input name="name" label="Item name" :value="$item->name" required maxlength="255" placeholder="Event T-shirt" :id="$prefix.'-name'"/>
<x-form.input name="description" label="Description" :value="$item->description" maxlength="1000" :id="$prefix.'-description'"/>
<div class="grid gap-4 sm:grid-cols-3">
    <x-form.input name="price" :label="'Price ('.config('scout.currency').')'" type="number" step="0.01" min="0" :value="$item->price ?? '0.00'" required :id="$prefix.'-price'"/>
    <x-form.input name="stock" label="Stock" type="number" min="0" :value="$item->stock" help="Empty = unlimited" :id="$prefix.'-stock'"/>
    <x-form.input name="max_per_registration" label="Max per scout" type="number" min="1" max="100" :value="$item->max_per_registration" required :id="$prefix.'-max'"/>
</div>
<x-form.input name="sizes" label="Sizes" :value="implode(', ', $item->sizeList())" placeholder="S, M, L, XL" help="Comma separated. Leave empty if the item has no sizes." :id="$prefix.'-sizes'"/>
<input type="hidden" name="active" value="0">
<x-form.checkbox name="active" label="Available to order" :checked="$item->active ?? true"/>
