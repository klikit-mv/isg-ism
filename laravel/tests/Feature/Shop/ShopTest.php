<?php

namespace Tests\Feature\Shop;

use App\Enums\FeeStatus;
use App\Enums\Permission;
use App\Enums\PurchaseStatus;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ShopItem;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function item(int $stock = 5, string $price = '10.00'): ShopItem
    {
        return ShopItem::query()->create(['name' => 'Scarf', 'price' => $price, 'stock_qty' => $stock, 'status' => 'Active']);
    }

    private function purchase(ShopItem $item, Student $student, int $quantity = 2): Purchase
    {
        $this->actingAs($this->parentOf($student))->post("/shop/{$item->uuid}/buy", ['student' => $student->uuid, 'quantity' => $quantity]);

        return Purchase::query()->latest('id')->firstOrFail();
    }

    private function payOnline(Purchase $purchase, string $amount)
    {
        $parent = User::query()->whereHas('parentLinks', fn ($q) => $q->where('student_id', $purchase->student_id))->firstOrFail();

        return $this->actingAs($parent)->post('/payments', [
            'payable_type' => 'purchase', 'payable_id' => $purchase->uuid, 'amount' => $amount, 'method' => 'online',
            'proof' => UploadedFile::fake()->image('proof.png'),
        ]);
    }

    public function test_buying_snapshots_the_line_and_does_not_touch_stock(): void
    {
        $item = $this->item();
        $purchase = $this->purchase($item, Student::factory()->create());

        $this->assertSame('20.00', $purchase->total_amount);
        $this->assertSame(PurchaseStatus::PendingPayment, $purchase->purchase_status);
        $this->assertSame('Scarf', $purchase->items->first()->item_name_snapshot);
        $this->assertSame(5, $item->fresh()->stock_qty);
    }

    public function test_stock_decrements_exactly_once_when_fully_paid(): void
    {
        $item = $this->item();
        $purchase = $this->purchase($item, Student::factory()->create());
        $admin = $this->admin();

        $this->payOnline($purchase, '10.00');
        $this->payOnline($purchase, '10.00');
        [$first, $second] = Payment::query()->orderBy('id')->get();

        $this->actingAs($admin)->post("/payments/{$first->uuid}/approve");
        $this->assertSame(5, $item->fresh()->stock_qty);
        $this->assertSame(FeeStatus::Partial, $purchase->fresh()->payment_status);

        $this->actingAs($admin)->post("/payments/{$second->uuid}/approve");
        $purchase->refresh();
        $this->assertSame(3, $item->fresh()->stock_qty);
        $this->assertTrue($purchase->stock_decremented);
        $this->assertSame(PurchaseStatus::Confirmed, $purchase->purchase_status);
        $this->assertSame(1, StockMovement::query()->count());

        app(InventoryService::class)->decrementForPayable($purchase);
        $this->assertSame(3, $item->fresh()->stock_qty);
    }

    public function test_awaiting_payment_moves_purchase_to_verification(): void
    {
        $purchase = $this->purchase($this->item(), Student::factory()->create());

        $this->payOnline($purchase, '20.00');

        $this->assertSame(PurchaseStatus::PaymentVerification, $purchase->fresh()->purchase_status);
    }

    public function test_staff_cash_confirms_immediately(): void
    {
        $item = $this->item();
        $purchase = $this->purchase($item, Student::factory()->create());

        $this->actingAs($this->admin())->post('/payments', ['payable_type' => 'purchase', 'payable_id' => $purchase->uuid, 'amount' => '20', 'method' => 'cash']);

        $this->assertSame(PurchaseStatus::Confirmed, $purchase->fresh()->purchase_status);
        $this->assertSame(3, $item->fresh()->stock_qty);
    }

    public function test_approval_is_blocked_when_stock_ran_out(): void
    {
        $item = $this->item(stock: 2);
        $first = $this->purchase($item, Student::factory()->create(), 2);
        $second = $this->purchase($item, Student::factory()->create(), 2);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/payments', ['payable_type' => 'purchase', 'payable_id' => $first->uuid, 'amount' => '20', 'method' => 'cash']);
        $this->payOnline($second, '20.00');
        $payment = Payment::query()->where('payable_id', $second->id)->firstOrFail();

        $this->actingAs($admin)->post("/payments/{$payment->uuid}/approve")->assertSessionHas('error');

        $this->assertSame(0, $item->fresh()->stock_qty);
        $this->assertSame('AwaitingVerification', $payment->fresh()->status->value);
        $this->assertFalse($second->fresh()->stock_decremented);
    }

    public function test_unpaid_purchases_cannot_be_marked_ready_or_delivered(): void
    {
        $purchase = $this->purchase($this->item(), Student::factory()->create());
        $admin = $this->admin();

        $this->actingAs($admin)->post("/purchases/{$purchase->uuid}/ready")->assertSessionHas('error');
        $this->actingAs($admin)->post("/purchases/{$purchase->uuid}/deliver")->assertSessionHas('error');
        $this->assertSame(PurchaseStatus::PendingPayment, $purchase->fresh()->purchase_status);
    }

    public function test_paid_purchase_is_made_ready_and_delivered(): void
    {
        $student = Student::factory()->create(['name' => 'Collector']);
        $purchase = $this->purchase($this->item(), $student);
        $staff = $this->leader(Permission::ProcessDelivery);
        $this->actingAs($this->admin())->post('/payments', ['payable_type' => 'purchase', 'payable_id' => $purchase->uuid, 'amount' => '20', 'method' => 'cash']);

        $this->actingAs($staff)->post("/purchases/{$purchase->uuid}/ready")->assertSessionHas('success');
        $this->actingAs($staff)->post("/purchases/{$purchase->uuid}/deliver")->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame(PurchaseStatus::Delivered, $purchase->purchase_status);
        $this->assertSame('Collector', $purchase->recipient);
        $this->assertSame($staff->id, $purchase->delivered_by);
    }

    public function test_buying_is_refused_when_closed_out_of_scope_or_short_of_stock(): void
    {
        $item = $this->item(stock: 1);
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);

        $this->actingAs($parent)->post("/shop/{$item->uuid}/buy", ['student' => $student->uuid, 'quantity' => 2])->assertSessionHas('error');
        $this->actingAs($parent)->post("/shop/{$item->uuid}/buy", ['student' => Student::factory()->create()->uuid, 'quantity' => 1])->assertSessionHas('error');

        app(SettingsService::class)->set('shop_enabled', '0');
        $this->actingAs($parent)->post("/shop/{$item->uuid}/buy", ['student' => $student->uuid, 'quantity' => 1])
            ->assertSessionHas('error', 'The shop is closed at the moment.');

        $this->assertSame(0, Purchase::query()->count());
    }

    public function test_catalogue_shows_placeholder_and_parents_cannot_manage_items(): void
    {
        $item = $this->item();
        $parent = $this->parentOf(Student::factory()->create());

        $this->actingAs($parent)->get('/shop')->assertOk()->assertSee('data-testid="placeholder"', false);
        $this->actingAs($parent)->post('/shop', ['name' => 'Hack', 'price' => 1, 'stock_qty' => 1])->assertForbidden();
        $this->actingAs($parent)->put("/shop/{$item->uuid}", ['name' => 'Hack', 'price' => 1, 'stock_qty' => 1, 'status' => 'Active'])->assertForbidden();
    }

    public function test_shop_manager_adds_item_with_image(): void
    {
        $manager = $this->leader(Permission::ManageShop);

        $this->actingAs($manager)->post('/shop', ['name' => 'Woggle', 'price' => '4.50', 'stock_qty' => 10, 'image' => UploadedFile::fake()->image('w.png')])
            ->assertSessionHas('success');

        $item = ShopItem::query()->where('name', 'Woggle')->firstOrFail();
        Storage::disk('public')->assertExists($item->image_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'shop_item.created', 'entity_id' => $item->uuid]);
    }

    public function test_inactive_items_are_hidden_from_non_managers(): void
    {
        ShopItem::query()->create(['name' => 'Retired Hat', 'price' => '5', 'stock_qty' => 1, 'status' => 'Inactive']);

        $this->actingAs($this->parentOf(Student::factory()->create()))->get('/shop')->assertDontSee('Retired Hat');
        $this->actingAs($this->leader(Permission::ManageShop))->get('/shop')->assertSee('Retired Hat');
    }

    public function test_buyer_cancels_unpaid_purchase_but_not_paid_one(): void
    {
        $student = Student::factory()->create();
        $parent = $this->parentOf($student);
        $item = $this->item();
        $this->actingAs($parent)->post("/shop/{$item->uuid}/buy", ['student' => $student->uuid, 'quantity' => 1]);
        $purchase = Purchase::query()->firstOrFail();

        $this->actingAs($parent)->post("/purchases/{$purchase->uuid}/cancel")->assertSessionHas('success');
        $this->assertSame(PurchaseStatus::Cancelled, $purchase->fresh()->purchase_status);

        $this->actingAs($parent)->post("/shop/{$item->uuid}/buy", ['student' => $student->uuid, 'quantity' => 1]);
        $paid = Purchase::query()->latest('id')->firstOrFail();
        $this->actingAs($this->admin())->post('/payments', ['payable_type' => 'purchase', 'payable_id' => $paid->uuid, 'amount' => '10', 'method' => 'cash']);

        $this->actingAs($parent)->post("/purchases/{$paid->uuid}/cancel")->assertSessionHas('error');
        $this->actingAs($this->parentOf(Student::factory()->create()))->post("/purchases/{$paid->uuid}/cancel")->assertForbidden();
    }
}
