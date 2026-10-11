<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnnualFeeYear extends Model
{
    use HasUuid;

    protected $fillable = ['year', 'amount', 'status', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'amount' => 'decimal:2',
            'status' => RecordStatus::class,
        ];
    }

    /**
     * @return HasMany<AnnualFee, $this>
     */
    public function fees(): HasMany
    {
        return $this->hasMany(AnnualFee::class);
    }
}
