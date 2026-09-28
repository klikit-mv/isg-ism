<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\EventRegistrationStatus;
use App\Enums\FeeStatus;
use App\Enums\PaymentMethod;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class EventRegistration extends Model implements Payable
{
    use HasUuid;

    protected $fillable = [
        'event_id', 'student_id', 'registered_by', 'status', 'payment_option', 'fee_amount', 'items_amount',
        'total_amount', 'paid_amount', 'outstanding_amount', 'payment_status', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRegistrationStatus::class,
            'payment_option' => PaymentMethod::class,
            'fee_amount' => 'decimal:2',
            'items_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'payment_status' => FeeStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }

    /**
     * @return HasMany<EventRegistrationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(EventRegistrationItem::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function isActive(): bool
    {
        return $this->status === EventRegistrationStatus::Registered;
    }

    public function payableTypeKey(): string
    {
        return 'event_registration';
    }

    public function amountDue(): string
    {
        return (string) $this->total_amount;
    }

    public function payableStudentId(): ?int
    {
        return $this->student_id;
    }

    public function payableDescription(): string
    {
        return 'Event — '.($this->event?->name ?? 'registration').' ('.($this->student?->name ?? '').')';
    }
}
