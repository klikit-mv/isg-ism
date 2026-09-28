@php $suffix = $badge->uuid ?? 'new'; @endphp
<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="Name" :value="$badge->name" required :id="'bname-'.$suffix"/>
    <x-form.input name="code" label="Code" :value="$badge->code" required class="uppercase" :id="'bcode-'.$suffix"/>
    <x-form.select name="category" label="Category" :options="\App\Models\Badge::CATEGORIES" :value="$badge->category ?? 'proficiency'" :id="'bcat-'.$suffix"/>
    <x-form.select name="section" label="Section" :options="\App\Enums\ScoutSection::options()" :value="$badge->section" placeholder="None" :id="'bsection-'.$suffix"/>
    <x-form.select name="certificate_template_id" label="Certificate template" :options="$templates" :value="$badge->certificate_template_id" placeholder="Default badge template" :id="'btpl-'.$suffix"/>
    <x-form.input name="number_prefix" label="Number prefix" :value="$badge->number_prefix" help="Used when the badge has no section." :id="'bprefix-'.$suffix"/>
    <x-form.input name="next_number" label="Next number this year" type="number" min="1" help="Leave blank to keep the sequence. For a proficiency badge this sets the shared number for the whole section." :id="'bnext-'.$suffix"/>
</div>
<p class="text-xs text-gray-500 dark:text-gray-400">Proficiency badges need a section. All proficiency badges of a section share one certificate number sequence that continues for the whole year (e.g. SCOUT-2026-0001, 0002…) and restarts on 1 January. Other badges have their own sequence using the prefix or code.</p>
<x-form.textarea name="description" label="Description" :value="$badge->description"/>
<x-form.file-drop name="image" label="Picture (optional)" accept="image/png,image/jpeg,image/webp"/>
