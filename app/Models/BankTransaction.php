<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    use HasUuid;

    public const DEPOSIT = 'deposit';

    public const EXPENSE = 'expense';

    protected $fillable = [
        'bank_account_id', 'type', 'amount', 'transaction_date', 'party', 'purpose', 'details', 'reference',
        'attachment_disk', 'attachment_path', 'attachment_name', 'attachment_mime', 'recorded_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date'];
    }

    /**
     * @return BelongsTo<BankAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by')->withTrashed();
    }

    public function isDeposit(): bool
    {
        return $this->type === self::DEPOSIT;
    }
}
