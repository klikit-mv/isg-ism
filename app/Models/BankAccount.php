<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'bank_name', 'account_name', 'account_number', 'opening_balance', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2'];
    }

    /**
     * @return HasMany<BankTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function totalDeposits(): string
    {
        return Money::normalize($this->transactions()->where('type', BankTransaction::DEPOSIT)->sum('amount'));
    }

    public function totalExpenses(): string
    {
        return Money::normalize($this->transactions()->where('type', BankTransaction::EXPENSE)->sum('amount'));
    }

    /**
     * Always derived from the entries: opening balance + deposits − spending.
     */
    public function balance(): string
    {
        return Money::sub(Money::add($this->opening_balance, $this->totalDeposits()), $this->totalExpenses());
    }
}
