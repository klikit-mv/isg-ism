@php $suffix = $badge->uuid ?? 'new'; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="Name" :value="$badge->name" required :id="'bname-'.$suffix"/>
    <x-form.input name="code" label="Code" :value="$badge->code" required class="uppercase" :id="'bcode-'.$suffix"/>
    <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="$badge->section" placeholder="Any" :id="'bsection-'.$suffix"/>
    <x-form.input name="category" label="Category" :value="$badge->category" placeholder="proficiency" :id="'bcat-'.$suffix"/>
    <x-form.select name="certificate_template_id" label="Certificate template" :options="$templates" :value="$badge->certificate_template_id" placeholder="Default badge template" :id="'btpl-'.$suffix"/>
    <x-form.input name="number_prefix" label="Number prefix" :value="$badge->number_prefix" help="Used when the badge has no section." :id="'bprefix-'.$suffix"/>
    <x-form.input name="next_number" label="Next number this year" type="number" min="1" help="Leave blank to keep the sequence." :id="'bnext-'.$suffix"/>
</div>
<x-form.textarea name="description" label="Description" :value="$badge->description"/>
<x-form.file-drop name="image" label="Picture (optional)" accept="image/png,image/jpeg,image/webp"/>
