<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Enums\ShopItemStatus;
use App\Models\ShopItem;
use App\Models\Student;
use App\Services\AuditLogService;
use App\Services\GoogleDrivePhotoService;
use App\Services\LeaderScopeService;
use App\Services\PurchaseService;
use App\Services\SettingsService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private AuditLogService $audit, private GoogleDrivePhotoService $photos) {}

    public function index(Request $request, LeaderScopeService $scope, SettingsService $settings): View
    {
        $user = $request->user();
        $manager = $user->hasPermission(Permission::ManageShop);

        $items = ShopItem::query()
            ->when(! $manager, fn ($q) => $q->where('status', ShopItemStatus::Active->value))
            ->when($manager && $request->query('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(Pagination::MAX)
            ->withQueryString();

        $students = $scope->constrainStudents(Student::query()->active(), $user)->orderBy('name')->get(['id', 'uuid', 'name', 'status'])
            ->filter(fn (Student $s) => $scope->canAccessStudent($user, $s));

        return view('shop.index', [
            'items' => $items,
            'manager' => $manager,
            'shopEnabled' => $settings->shopEnabled(),
            'students' => $students->pluck('name', 'uuid')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->photos->store($request->file('image'), 'shop');
        }

        $item = ShopItem::query()->create($data + ['created_by' => $request->user()->id]);
        $this->audit->record('shop_item.created', $item, ['name' => $item->name, 'price' => $item->price, 'stock' => $item->stock_qty]);

        return back()->with('success', "{$item->name} was added to the shop.");
    }

    public function update(Request $request, ShopItem $item): RedirectResponse
    {
        $this->authorizeManager($request);
        $data = $this->validated($request, $item);

        if ($request->hasFile('image')) {
            $old = $item->image_path;
            $data['image_path'] = $this->photos->store($request->file('image'), 'shop');
            $this->photos->delete($old);
        }

        $item->update($data);
        $this->audit->record('shop_item.updated', $item, ['name' => $item->name, 'price' => $item->price, 'stock' => $item->stock_qty]);

        return back()->with('success', "{$item->name} was saved.");
    }

    public function destroy(Request $request, ShopItem $item): RedirectResponse
    {
        $this->authorizeManager($request);
        $this->audit->record('shop_item.deleted', $item, ['name' => $item->name]);
        $item->delete();

        return back()->with('success', 'The item was removed from the shop.');
    }

    public function buy(Request $request, ShopItem $item, PurchaseService $purchases): RedirectResponse
    {
        $data = $request->validate([
            'student' => ['required', 'uuid'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $student = Student::query()->where('uuid', $data['student'])->firstOrFail();
        $purchases->create($item, $student, (int) $data['quantity'], $request->user());

        return redirect()->route('purchases.index')->with('success', 'Your order was placed. Pay for it below to confirm it.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ShopItem $item = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'stock_qty' => ['required', 'integer', 'min:0', 'max:1000000'],
            'status' => [$item ? 'required' : 'nullable', Rule::enum(ShopItemStatus::class)],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('scout.shop_image_max_kb')],
        ]) + ['status' => ShopItemStatus::Active->value];
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()->hasPermission(Permission::ManageShop), 403);
    }
}
