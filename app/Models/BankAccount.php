<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUuid;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class BankAccount extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'bank_name', 'account_name', 'account_number', 'opening_balance', 'status', 'receives_online', 'online_from', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['opening_balance' => 'decimal:2', 'receives_online' => 'boolean', 'online_from' => 'date'];
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
     * Verified online payments (fees, shop, events) received by this account, from the date it started counting them.
     *
     * @return Builder<Payment>
     */
    public function onlinePayments(): Builder
    {
        $query = Payment::query()->where('method', PaymentMethod::Online->value)->where('status', PaymentStatus::Paid->value);

        if (! $this->receives_online) {
            return $query->whereRaw('1 = 0');
        }

        return $query->when($this->online_from, fn ($q, $from) => $q->where('verified_at', '>=', Carbon::parse($from->toDateString(), config('scout.timezone'))->startOfDay()->utc()));
    }

    public function totalOnline(): string
    {
        return Money::normalize($this->onlinePayments()->sum('amount'));
    }

    /**
     * Always derived from the entries: opening balance + deposits + verified online payments − spending.
     */
    public function balance(): string
    {
        return Money::sub(Money::add(Money::add($this->opening_balance, $this->totalDeposits()), $this->totalOnline()), $this->totalExpenses());
    }
}
