<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentVerificationController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(): View
    {
        $this->authorize('verify', Payment::class);

        return view('finance.verification', [
            'payments' => Payment::query()
                ->where('status', PaymentStatus::AwaitingVerification->value)
                ->with('student', 'submitter', 'proof', 'payable')
                ->orderBy('submitted_at')
                ->paginate(Pagination::MAX),
        ]);
    }

    public function approve(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('verify', Payment::class);
        $this->payments->approve($payment, $request->user());

        return back()->with('success', 'The payment was approved.');
    }

    public function reject(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('verify', Payment::class);
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->payments->reject($payment, $request->user(), $data['reason']);

        return back()->with('success', 'The payment was rejected.');
    }
}
