<?php

namespace App\Models;

use App\Enums\EventRegistrationStatus;
use App\Enums\EventStatus;
use App\Enums\ScoutSection;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'description', 'location', 'starts_at', 'ends_at', 'registration_closes_at',
        'fee', 'capacity', 'sections', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'fee' => 'decimal:2',
            'capacity' => 'integer',
            'sections' => 'array',
            'status' => EventStatus::class,
        ];
    }

    /**
     * @return HasMany<EventItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventItem::class)->orderBy('name');
    }

    /**
     * @return HasMany<EventRegistration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return list<ScoutSection>
     */
    public function eligibleSections(): array
    {
        return array_values(array_filter(array_map(fn ($s) => ScoutSection::tryFrom((string) $s), $this->sections ?? [])));
    }

    public function isOpenForSection(ScoutSection $section): bool
    {
        $sections = $this->eligibleSections();

        return $sections === [] || in_array($section, $sections, true);
    }

    public function sectionsLabel(): string
    {
        $sections = $this->eligibleSections();

        return $sections === [] ? 'All sections' : implode(', ', array_map(fn (ScoutSection $s) => $s->value, $sections));
    }

    public function registeredCount(): int
    {
        return $this->registrations()->where('status', EventRegistrationStatus::Registered->value)->count();
    }

    /**
     * Open, and the registration deadline (if any) has not passed.
     */
    public function acceptsRegistrations(): bool
    {
        return $this->status === EventStatus::Open
            && ($this->registration_closes_at === null || $this->registration_closes_at->isFuture());
    }
}
