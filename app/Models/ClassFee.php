<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\FeeStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ClassFee extends Model implements Payable
{
    use HasUuid;

    protected $fillable = [
        'activity_id', 'student_id', 'amount', 'paid_amount', 'outstanding_amount',
        'status', 'due_date', 'voided_at', 'created_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'status' => FeeStatus::class,
            'due_date' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function payableTypeKey(): string
    {
        return 'class_fee';
    }

    public function amountDue(): string
    {
        return (string) $this->amount;
    }

    public function payableStudentId(): ?int
    {
        return $this->student_id;
    }

    public function payableDescription(): string
    {
        return 'Class fee — '.($this->activity?->name ?? 'activity');
    }
}
