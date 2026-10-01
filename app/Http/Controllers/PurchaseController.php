<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Purchase;
use App\Services\LeaderScopeService;
use App\Services\PurchaseService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request, LeaderScopeService $scope): View
    {
        $user = $request->user();

        $query = Purchase::query()
            ->select('purchases.*')
            ->join('students', 'students.id', '=', 'purchases.student_id')
            ->with('student', 'items', 'creator')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('students.name', 'like', "%{$term}%")
                ->orWhereHas('items', fn ($i) => $i->where('item_name_snapshot', 'like', "%{$term}%"))))
            ->when($request->query('payment_status'), fn ($q, $status) => $q->where('purchases.payment_status', $status))
            ->when($request->query('purchase_status'), fn ($q, $status) => $q->where('purchases.purchase_status', $status));

        if (! $user->hasPermission(Permission::ManageShop) && ! $user->hasPermission(Permission::ProcessDelivery)) {
            $scope->constrainByStudent($query, $user, 'purchases.student_id');
        }

        return view('shop.purchases', [
            'purchases' => $query->orderByDesc('purchases.created_at')->paginate(Pagination::MAX)->withQueryString(),
            'canDeliver' => $this->purchases->canProcessDelivery($user),
        ]);
    }

    public function ready(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->purchases->markReady($purchase, $request->user());

        return back()->with('success', 'The purchase is ready for collection.');
    }

    public function deliver(Request $request, Purchase $purchase): RedirectResponse
    {
        $data = $request->validate(['recipient' => ['nullable', 'string', 'max:255']]);
        $this->purchases->deliver($purchase, $request->user(), $data['recipient'] ?? null);

        return back()->with('success', 'The purchase was delivered.');
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('cancel', $purchase);
        $this->purchases->cancel($purchase, $request->user());

        return back()->with('success', 'The purchase was cancelled.');
    }
}
