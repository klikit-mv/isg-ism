<?php

namespace App\Models;

use App\Enums\ShopItemStatus;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ShopItem extends Model
{
    use HasUuid, SoftDeletes;

    protected $fillable = ['name', 'description', 'price', 'image_path', 'status', 'stock_qty', 'created_by', 'legacy_id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'status' => ShopItemStatus::class,
            'stock_qty' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ShopItemStatus::Active;
    }
}
