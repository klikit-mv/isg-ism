<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationItem extends Model
{
    protected $fillable = ['event_registration_id', 'event_item_id', 'item_name', 'size', 'quantity', 'unit_price', 'total_amount'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<EventRegistration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'event_registration_id');
    }

    /**
     * @return BelongsTo<EventItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(EventItem::class, 'event_item_id');
    }
}
