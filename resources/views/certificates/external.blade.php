<x-app-layout title="Upload certificates">
    <x-page-header title="Upload certificates from another party" description="For certificates made by someone else, such as SAM. Scouts see them with their other certificates.">
        <x-slot:actions><a href="{{ route('certificates.index') }}" class="btn-secondary">Back</a></x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('certificates.external.stage') }}" enctype="multipart/form-data" class="card space-y-5" data-testid="external-form">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="issuer" label="Issued by" placeholder="e.g. SAM" required/>
            <x-form.input name="date_awarded" label="Date awarded" type="date" :value="scout_today()" :max="scout_today()" required/>
        </div>

        <div x-data="{ names: [], over: false }">
            <span class="label">PDF files</span>
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-8 text-center text-sm transition"
                :class="over ? 'border-navy-500 bg-navy-50 dark:bg-navy-900/30' : 'border-gray-300 dark:border-gray-600'"
                x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; $refs.input.files = $event.dataTransfer.files; names = Array.from($event.dataTransfer.files).map(f => f.name)">
                <svg class="mb-2 h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                <span class="text-gray-600 dark:text-gray-300" x-show="names.length === 0">Drop PDFs here, or click to choose many at once</span>
                <span class="font-medium text-gray-800 dark:text-gray-100" x-show="names.length > 0" x-text="names.length + ' file(s) chosen'"></span>
                <input x-ref="input" type="file" name="files[]" accept="application/pdf,.pdf" multiple required class="sr-only" x-on:change="names = Array.from($event.target.files).map(f => f.name)">
            </label>
            <ul class="mt-2 max-h-32 overflow-y-auto text-xs text-gray-500 dark:text-gray-400" x-show="names.length > 0"><template x-for="n in names"><li x-text="n"></li></template></ul>
            @error('files') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            @error('files.*') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tip: name each file with the scout's National ID, index number or name (for example <span class="font-mono">A1234567 SAM Award.pdf</span>) and the scout is picked for you on the next step. Up to {{ \App\Services\ExternalCertificateService::MAX_FILES }} files at a time (your server may allow fewer).</p>
        </div>

        <button class="btn-primary">Continue</button>
    </form>
</x-app-layout>
