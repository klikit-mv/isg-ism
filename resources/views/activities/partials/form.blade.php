@php
    $activity ??= new \App\Models\Activity(['date' => now()]);
    $selectedSections = old('sections', $activity->exists ? array_map(fn ($s) => $s->value, $activity->sections()) : []);
@endphp
<div class="space-y-4" x-data="{ charge: @js((bool) old('charge_fee', $activity->charge_fee)), all: @js((bool) old('all_students', $activity->all_students)) }">
    <x-form.input name="name" label="Name" :value="$activity->name" required/>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.input name="date" label="Date" type="date" :value="$activity->date?->format('Y-m-d')" required/>
        <x-form.select name="certificate_template_id" label="Certificate template (optional)" :options="$templateOptions" :value="$activity->certificate_template_id" placeholder="None"/>
    </div>
    <x-form.textarea name="details" label="Details" :value="$activity->details"/>

    <fieldset class="space-y-3 rounded-lg border border-gray-200 p-3 dark:border-gray-700">
        <legend class="px-1 text-sm font-medium">Who is expected</legend>
        <input type="hidden" name="all_students" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="all_students" value="1" x-model="all" class="rounded border-gray-300 text-navy-600"> All scouts</label>
        <div x-show="! all" class="space-y-3">
            <div>
                <span class="label">Sections</span>
                <div class="flex flex-wrap gap-3">
                    @foreach (\App\Enums\ScoutSection::cases() as $section)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sections[]" value="{{ $section->value }}" @checked(in_array($section->value, $selectedSections, true)) class="rounded border-gray-300 text-navy-600"> {{ $section->value }}</label>
                    @endforeach
                </div>
                @error('sections')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <x-form.multi-pick name="groups[]" label="Groups" :options="$groupOptions" :selected="$activity->exists ? $activity->groups->pluck('id')->all() : []" placeholder="Search groups"/>
        </div>
    </fieldset>

    <div class="space-y-3">
        <input type="hidden" name="charge_fee" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="charge_fee" value="1" x-model="charge" class="rounded border-gray-300 text-navy-600"> Charge a class fee</label>
        <div x-show="charge">
            <x-form.input name="fee_amount" label="Fee amount" type="number" step="0.01" min="0" :value="$activity->fee_amount" help="Leave blank to use the default class fee."/>
        </div>
    </div>
</div>
