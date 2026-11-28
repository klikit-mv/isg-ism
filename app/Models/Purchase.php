<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\FeeStatus;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Purchase extends Model implements Payable
{
    use HasUuid;

    protected $fillable = [
        'student_id', 'created_by', 'total_amount', 'paid_amount', 'outstanding_amount',
        'payment_status', 'purchase_status', 'stock_decremented', 'delivered_by',
        'delivered_at', 'recipient', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
            'payment_status' => FeeStatus::class,
            'purchase_status' => PurchaseStatus::class,
            'stock_decremented' => 'boolean',
            'delivered_at' => 'datetime',
        ];
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by')->withTrashed();
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === FeeStatus::Paid;
    }

    public function payableTypeKey(): string
    {
        return 'purchase';
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
        return 'Shop purchase — '.$this->items->pluck('item_name_snapshot')->implode(', ');
    }
}
