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

    protected $fillable = ['event_id', 'name', 'description', 'price', 'sizes', 'stock', 'max_per_registration', 'active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sizes' => 'array',
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
