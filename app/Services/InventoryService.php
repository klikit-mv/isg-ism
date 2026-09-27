<?php

namespace App\Services;

use App\Contracts\Payable;
use App\Exceptions\InsufficientStock;
use App\Exceptions\ScoutException;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stock falls exactly once, when a purchase becomes fully paid.
 */
class InventoryService
{
    public function __construct(private AuditLogService $audit) {}

    public function assertPurchasable(ShopItem $item, int $quantity): void
    {
        if (! $item->isActive()) {
            throw new ScoutException("{$item->name} is not available.");
        }

        if ($quantity < 1) {
            throw new ScoutException('Choose a quantity of at least 1.');
        }

        if ($item->stock_qty < $quantity) {
            throw new InsufficientStock("Only {$item->stock_qty} of {$item->name} left in stock.");
        }
    }

    /**
     * Run inside the caller's transaction once a payable may have become fully paid.
     */
    public function decrementForPayable(Model&Payable $payable, ?User $actor = null): bool
    {
        if (! $payable instanceof Purchase) {
            return false;
        }

        return DB::transaction(function () use ($payable, $actor): bool {
            $purchase = Purchase::query()->whereKey($payable->id)->lockForUpdate()->firstOrFail();

            if ($purchase->stock_decremented || ! $purchase->isFullyPaid()) {
                return false;
            }

            foreach ($purchase->items as $line) {
                $this->decrementItem($line->shop_item_id, $line->quantity, $purchase, $actor);
            }

            $purchase->forceFill(['stock_decremented' => true])->save();
            $payable->setRawAttributes($purchase->getAttributes(), true);

            return true;
        });
    }

    public function decrementItem(int $shopItemId, int $quantity, Purchase $purchase, ?User $actor = null): void
    {
        $item = ShopItem::query()->withTrashed()->whereKey($shopItemId)->lockForUpdate()->firstOrFail();

        if ($item->stock_qty < $quantity) {
            throw new InsufficientStock("Not enough stock of {$item->name} ({$item->stock_qty} left, {$quantity} needed). Reject this payment and refund the buyer, or restock first.");
        }

        $item->decrement('stock_qty', $quantity);

        StockMovement::query()->create([
            'shop_item_id' => $item->id,
            'type' => StockMovement::DECREMENT,
            'quantity' => $quantity,
            'reference_type' => 'purchase',
            'reference_id' => $purchase->id,
            'actor_user_id' => $actor?->id,
        ]);

        $this->audit->record('stock.decremented', $item, ['quantity' => $quantity, 'purchase' => $purchase->uuid], $actor);
    }
}
