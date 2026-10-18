@php
    $editing = $event->exists;
    $chosen = old('sections', $event->sections ?? []);
    $local = fn ($value) => $value ? $value->copy()->setTimezone(config('scout.timezone'))->format('Y-m-d\TH:i') : null;
@endphp
<x-app-layout :title="$editing ? 'Edit event' : 'New event'">
    <x-page-header :title="$editing ? 'Edit '.$event->name : 'New event'" description="Set the date, fee and who can join. Pre-order items like T-shirts are added on the event page."/>

    <form method="POST" action="{{ $editing ? route('events.update', $event) : route('events.store') }}" class="card max-w-3xl space-y-4">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-form.input name="name" label="Event name" :value="$event->name" required maxlength="255"/>
        <x-form.textarea name="description" label="Description" :value="$event->description" rows="4"/>
        <x-form.input name="location" label="Location" :value="$event->location"/>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input name="starts_at" label="Starts" type="datetime-local" :value="$local($event->starts_at)" required/>
            <x-form.input name="ends_at" label="Ends" type="datetime-local" :value="$local($event->ends_at)"/>
            <x-form.input name="registration_closes_at" label="Registration closes" type="datetime-local" :value="$local($event->registration_closes_at)" help="Leave empty to keep open until you close it."/>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input name="fee" :label="'Registration fee ('.config('scout.currency').')'" type="number" step="0.01" min="0" :value="$event->fee" required help="0 for a free event. Pre-order items are charged on top."/>
            <x-form.input name="capacity" label="Places" type="number" min="1" :value="$event->capacity" help="Leave empty for no limit."/>
        </div>

        <fieldset>
            <legend class="label">Open to sections</legend>
            <div class="flex flex-wrap gap-4">
                @foreach (\App\Enums\ScoutSection::options() as $value => $label)
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input type="checkbox" name="sections[]" value="{{ $value }}" @checked(in_array($value, $chosen, true))
                            class="rounded border-gray-300 text-navy-600 focus:ring-navy-500 dark:border-gray-600 dark:bg-gray-900">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Tick none to open it to every section.</p>
            @error('sections.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ $editing ? route('events.show', $event) : route('events.index') }}" class="btn-secondary">Cancel</a>
            <button type="submit" class="btn-primary">{{ $editing ? 'Save event' : 'Create event' }}</button>
        </div>
    </form>
</x-app-layout>
