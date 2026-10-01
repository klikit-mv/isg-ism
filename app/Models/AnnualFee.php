<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\FeeStatus;
use App\Enums\PersonType;
use App\Enums\ScoutSection;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AnnualFee extends Model implements Payable
{
    use HasUuid;

    protected $fillable = [
        'annual_fee_year_id', 'student_id', 'user_id', 'person_type', 'section',
        'amount', 'paid_amount', 'outstanding_amount', 'status', 'created_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'status' => FeeStatus::class,
            'person_type' => PersonType::class,
            'section' => ScoutSection::class,
        ];
    }

    /**
     * @return BelongsTo<AnnualFeeYear, $this>
     */
    public function feeYear(): BelongsTo
    {
        return $this->belongsTo(AnnualFeeYear::class, 'annual_fee_year_id');
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function personName(): string
    {
        return $this->person_type === PersonType::Leader
            ? (string) $this->user?->name
            : (string) $this->student?->name;
    }

    public function payableTypeKey(): string
    {
        return 'annual_fee';
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
        return 'Annual fee '.($this->feeYear?->year ?? '');
    }
}
