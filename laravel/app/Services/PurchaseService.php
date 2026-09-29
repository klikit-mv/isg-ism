<?php

namespace App\Services;

use App\Enums\FeeStatus;
use App\Enums\Permission;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseNotDeliverable;
use App\Exceptions\ScoutException;
use App\Exceptions\StudentNotAccessible;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ShopItem;
use App\Models\Student;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        private SettingsService $settings,
        private LeaderScopeService $scope,
        private InventoryService $inventory,
        private AuditLogService $audit,
    ) {}

    public function create(ShopItem $item, Student $student, int $quantity, User $actor): Purchase
    {
        if (! $this->settings->shopEnabled()) {
            throw new ScoutException('The shop is closed at the moment.');
        }

        if (! $this->scope->canAccessStudent($actor, $student)) {
            throw new StudentNotAccessible('You cannot buy for this scout.');
        }

        return DB::transaction(function () use ($item, $student, $quantity, $actor): Purchase {
            $locked = ShopItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->inventory->assertPurchasable($locked, $quantity);

            $total = Money::mul($locked->price, $quantity);

            $purchase = Purchase::query()->create([
                'student_id' => $student->id,
                'created_by' => $actor->id,
                'total_amount' => $total,
                'paid_amount' => '0.00',
                'outstanding_amount' => $total,
                'payment_status' => FeeStatus::Pending,
                'purchase_status' => PurchaseStatus::PendingPayment,
            ]);

            PurchaseItem::query()->create([
                'purchase_id' => $purchase->id,
                'shop_item_id' => $locked->id,
                'item_name_snapshot' => $locked->name,
                'quantity' => $quantity,
                'unit_price' => $locked->price,
                'total_amount' => $total,
            ]);

            $this->audit->record('purchase.created', $purchase, ['item' => $locked->name, 'quantity' => $quantity, 'total' => $total], $actor);

            return $purchase;
        });
    }

    public function canProcessDelivery(User $user): bool
    {
        return $user->isActive() && $user->hasPermission(Permission::ProcessDelivery);
    }

    public function markReady(Purchase $purchase, User $actor): void
    {
        $this->assertDeliveryStaff($actor);

        DB::transaction(function () use ($purchase, $actor): void {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isFullyPaid() || $locked->purchase_status !== PurchaseStatus::Confirmed) {
                throw new PurchaseNotDeliverable('Only fully paid, confirmed purchases can be marked ready for collection.');
            }

            $locked->update(['purchase_status' => PurchaseStatus::ReadyForCollection]);
            $this->audit->record('purchase.ready', $locked, [], $actor);
        });
    }

    public function deliver(Purchase $purchase, User $actor, ?string $recipient = null): void
    {
        $this->assertDeliveryStaff($actor);

        DB::transaction(function () use ($purchase, $actor, $recipient): void {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isFullyPaid() || ! in_array($locked->purchase_status, [PurchaseStatus::Confirmed, PurchaseStatus::ReadyForCollection], true)) {
                throw new PurchaseNotDeliverable('Unpaid purchases cannot be delivered.');
            }

            $locked->update([
                'purchase_status' => PurchaseStatus::Delivered,
                'recipient' => filled($recipient) ? $recipient : $locked->student?->name,
                'delivered_by' => $actor->id,
                'delivered_at' => now(),
            ]);
            $this->audit->record('purchase.delivered', $locked, ['recipient' => $locked->recipient], $actor);
        });
    }

    /**
     * The buyer or shop staff may cancel until any money is approved.
     */
    public function cancel(Purchase $purchase, User $actor): void
    {
        DB::transaction(function () use ($purchase, $actor): void {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->purchase_status === PurchaseStatus::Cancelled) {
                return;
            }

            if (Money::isPositive($locked->paid_amount) || $locked->purchase_status->isPaidLifecycle() || $locked->payment_status === FeeStatus::AwaitingVerification) {
                throw new ScoutException('This purchase has a payment and cannot be cancelled.');
            }

            $locked->update(['purchase_status' => PurchaseStatus::Cancelled]);
            $this->audit->record('purchase.cancelled', $locked, [], $actor);
        });
    }

    private function assertDeliveryStaff(User $actor): void
    {
        if (! $this->canProcessDelivery($actor)) {
            throw new PurchaseNotDeliverable('Only admins or users with the Process delivery permission can do this.');
        }
    }
}
