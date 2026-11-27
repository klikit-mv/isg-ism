@php $suffix = $template->uuid ?? 'new'; @endphp
<x-form.input name="name" label="Name" :value="$template->name" required :id="'tname-'.$suffix"/>
<x-form.select name="type" label="Type" :options="\Illuminate\Support\Arr::except(\App\Enums\CertificateType::options(), 'external')" :value="$template->type" required :id="'ttype-'.$suffix"/>
<div x-data="{ source: @js(old('source', $template->exists ? $template->source() : 'builtin')), link: @js(old('google_slide', $template->usesGoogleSlides() ? $template->google_slide_id : '')), result: null, busy: false,
        async test() {
            this.busy = true; this.result = null;
            const res = await fetch(@js(route('certificate-templates.test-slide')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ google_slide: this.link }) });
            this.result = res.ok ? await res.json() : { ok: false, message: 'The test could not run. Try again in a minute.' };
            this.busy = false;
        } }" x-on:reset.window="if ($event.target.contains($el)) { source = ''; link = @js($template->usesGoogleSlides() ? $template->google_slide_id : ''); result = null; $nextTick(() => source = @js($template->exists ? $template->source() : 'builtin')) }" class="space-y-3">
    <div>
        <span class="label">Design comes from</span>
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach (['builtin' => 'Built-in layout', 'slides' => 'Google Slides'] as $value => $label)
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700" x-bind:class="source === '{{ $value }}' && 'border-navy-500 bg-navy-50 dark:bg-navy-900/40'">
                    <input type="radio" name="source" value="{{ $value }}" x-bind:checked="source === '{{ $value }}'" x-on:change="source = $event.target.value" class="text-navy-600"> {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div x-show="source === 'slides'" x-cloak>
        <label class="label" for="tslide-{{ $suffix }}">Google Slides link or ID</label>
        <div class="flex gap-2">
            <input id="tslide-{{ $suffix }}" name="google_slide" x-model="link" class="input" placeholder="https://docs.google.com/presentation/d/…" x-bind:disabled="source !== 'slides'">
            <button type="button" class="btn-secondary btn-sm" x-on:click="test()" :disabled="! link || busy">Test</button>
        </div>
        <p class="mt-1 text-xs" x-show="result" :class="result && result.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="result && result.message"></p>
        <p class="mt-1 text-xs text-gray-500">Put placeholders such as @{{name}}, @{{title}}, @{{date}} and @{{certno}} in the slide. Share the file with your connected Google account.</p>
    </div>

    <p class="text-xs text-gray-500" x-show="source === 'builtin'">The simple built-in layout is used.</p>
</div>
<x-form.select name="activity_id" label="Linked activity (general only)" :options="$activities" :value="$template->activity_id" placeholder="None" :id="'tact-'.$suffix"/>
<input type="hidden" name="active" value="0">
<x-form.checkbox name="active" label="Active" :checked="$template->active"/>
