<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasUuid;

    public const SOURCE_ROSTER = 'roster';

    protected $fillable = [
        'payable_type', 'payable_id', 'student_id', 'amount', 'method', 'source',
        'submitted_by', 'submitted_at', 'accepted_by', 'status', 'verified_by',
        'verified_at', 'rejection_reason', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by')->withTrashed();
    }

    /**
     * @return HasOne<PaymentProof, $this>
     */
    public function proof(): HasOne
    {
        return $this->hasOne(PaymentProof::class);
    }

    public function typeLabel(): string
    {
        return match ($this->payable_type) {
            'class_fee' => 'Class fee',
            'annual_fee' => 'Annual fee',
            'purchase' => 'Shop purchase',
            'event_registration' => 'Event',
            default => (string) $this->payable_type,
        };
    }
}
