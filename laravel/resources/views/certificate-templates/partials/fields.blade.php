@php $suffix = $template->uuid ?? 'new'; @endphp
<x-form.input name="name" label="Name" :value="$template->name" required :id="'tname-'.$suffix"/>
<x-form.select name="type" label="Type" :options="\App\Enums\CertificateType::options()" :value="$template->type" required :id="'ttype-'.$suffix"/>
<div x-data="{ link: @js($template->usesGoogleSlides() ? $template->google_slide_id : ''), result: null, busy: false,
        async test() {
            this.busy = true; this.result = null;
            const res = await fetch(@js(route('certificate-templates.test-slide')), { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ google_slide: this.link }) });
            this.result = res.ok ? await res.json() : { ok: false, message: 'The test could not run. Try again in a minute.' };
            this.busy = false;
        } }">
    <label class="label" for="tslide-{{ $suffix }}">Google Slides link or ID (optional)</label>
    <div class="flex gap-2">
        <input id="tslide-{{ $suffix }}" name="google_slide" x-model="link" class="input" placeholder="https://docs.google.com/presentation/d/…">
        <button type="button" class="btn-secondary btn-sm" x-on:click="test()" :disabled="! link || busy">Test</button>
    </div>
    <p class="mt-1 text-xs" x-show="result" :class="result && result.ok ? 'text-emerald-600' : 'text-rose-600'" x-text="result && result.message"></p>
    <p class="mt-1 text-xs text-gray-500">Leave blank to use the built-in layout.</p>
</div>
<x-form.select name="activity_id" label="Linked activity (general only)" :options="$activities" :value="$template->activity_id" placeholder="None" :id="'tact-'.$suffix"/>
<input type="hidden" name="active" value="0">
<x-form.checkbox name="active" label="Active" :checked="$template->active"/>
