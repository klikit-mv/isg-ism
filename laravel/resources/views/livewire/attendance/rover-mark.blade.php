<div class="space-y-6">
    @if ($flash)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200" role="status">{{ $flash }}</div>
    @endif
    @if ($error)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-900/30 dark:text-rose-200" role="alert">{{ $error }}</div>
    @endif

    <section>
        <h2 class="mb-2 font-semibold">Required Rovers <span class="text-sm font-normal text-gray-500">(on the roster)</span></h2>
        @if ($required->isEmpty())
            <x-empty message="No Rovers are on this roster."/>
        @else
            <x-table :headers="['Rover', 'Status']">
                @foreach ($required as $rover)
                    <tr wire:key="req-{{ $rover->id }}">
                        <td data-label="Rover" class="font-medium">{{ $rover->name }}</td>
                        <td data-label="Status">
                            <div class="flex flex-wrap justify-end gap-1 md:justify-start">
                                @foreach (\App\Enums\RoverAttendanceStatus::cases() as $status)
                                    <label class="cursor-pointer">
                                        <input type="radio" class="peer sr-only" value="{{ $status->value }}" wire:model="marks.{{ $rover->id }}">
                                        <span class="inline-block rounded-md border border-gray-300 px-2 py-1 text-xs peer-checked:border-navy-600 peer-checked:bg-navy-600 peer-checked:text-white dark:border-gray-600">{{ $status->value }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </section>

    <section>
        <h2 class="mb-2 font-semibold">Optional Rovers <span class="text-sm font-normal text-gray-500">(assistant leaders of targeted groups — Present only)</span></h2>
        @forelse ($optional->concat($additional) as $rover)
            <label wire:key="opt-{{ $rover->id }}" class="mb-1 flex items-center gap-2 text-sm">
                <input type="checkbox" value="Present" wire:model="marks.{{ $rover->id }}" class="rounded border-gray-300 text-navy-600"> {{ $rover->name }}
            </label>
        @empty
            <p class="text-sm text-gray-500">No optional Rovers.</p>
        @endforelse
    </section>

    <section class="card !p-4">
        <h2 class="mb-2 font-semibold">Other Rovers who attended</h2>
        <input type="search" wire:model.live.debounce.300ms="addSearch" class="input" placeholder="Search Rovers to add">
        <div class="mt-2 space-y-1">
            @foreach ($available as $rover)
                <div wire:key="av-{{ $rover->id }}" class="flex items-center justify-between text-sm">
                    <span>{{ $rover->name }}</span>
                    <button type="button" wire:click="addRover({{ $rover->id }})" class="btn-secondary btn-sm">Add as present</button>
                </div>
            @endforeach
        </div>
    </section>

    <div class="flex justify-end">
        <button type="button" wire:click="save" class="btn-primary">Save Rover register</button>
    </div>
</div>
