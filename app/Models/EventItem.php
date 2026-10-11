<?php

namespace App\Models;

use App\Enums\EventRegistrationStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventItem extends Model
{
    use HasUuid;

    protected $fillable = ['event_id', 'name', 'description', 'price', 'sizes', 'size_chart', 'size_guide', 'stock', 'max_per_registration', 'active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sizes' => 'array',
            'size_chart' => 'array',
            'stock' => 'integer',
            'max_per_registration' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class);
    }

    /**
     * @return list<string>
     */
    public function sizeList(): array
    {
        return array_values(array_filter(array_map('trim', $this->sizes ?? [])));
    }

    /**
     * Measurements per size, e.g. ['M' => 'Chest 38 in, Length 28 in'].
     *
     * @return array<string, string>
     */
    public function measurements(): array
    {
        $chart = $this->size_chart ?? [];

        return array_filter(array_combine($this->sizeList(), array_map(fn (string $size) => trim((string) ($chart[$size] ?? '')), $this->sizeList())) ?: []);
    }

    public function sizeLabel(string $size): string
    {
        $measurement = $this->measurements()[$size] ?? null;

        return $measurement ? "{$size} ({$measurement})" : $size;
    }

    /**
     * The sizes as typed in the item form: one per line, "size: measurements".
     */
    public function sizesText(): string
    {
        $chart = $this->measurements();

        if ($chart === []) {
            return implode(', ', $this->sizeList());
        }

        return implode("\n", array_map(fn (string $size) => isset($chart[$size]) ? "{$size}: {$chart[$size]}" : $size, $this->sizeList()));
    }

    /**
     * Quantity already ordered on active registrations.
     */
    public function orderedQuantity(?int $exceptRegistrationId = null): int
    {
        return (int) $this->orderLines()
            ->whereHas('registration', fn ($q) => $q->where('status', EventRegistrationStatus::Registered->value)
                ->when($exceptRegistrationId, fn ($w) => $w->whereKeyNot($exceptRegistrationId)))
            ->sum('quantity');
    }

    public function remaining(): ?int
    {
        return $this->stock === null ? null : max(0, $this->stock - $this->orderedQuantity());
    }
}
