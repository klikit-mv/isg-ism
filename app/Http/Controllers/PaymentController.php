<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Enums\Permission;
use App\Models\Payment;
use App\Services\LeaderScopeService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Support\Pagination;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments, private LeaderScopeService $scope) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = Payment::query()
            ->select('payments.*')
            ->leftJoin('students', 'students.id', '=', 'payments.student_id')
            ->with('student', 'submitter', 'verifier', 'proof')
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('payments.uuid', 'like', "%{$term}%")
                ->orWhere('students.name', 'like', "%{$term}%")
                ->orWhere('students.national_id', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $status) => $q->where('payments.status', $status))
            ->when($request->query('method'), fn ($q, $method) => $q->where('payments.method', $method));

        if (! $user->hasPermission(Permission::VerifyPayments)) {
            $ids = $this->scope->getLeaderStudentIds($user) ?? [];
            $query->where(fn ($w) => $w->whereIn('payments.student_id', $ids === [] ? [0] : $ids)->orWhere('payments.submitted_by', $user->id));
        }

        return view('finance.payments', [
            'payments' => $query->orderByDesc('payments.submitted_at')->paginate(Pagination::MAX)->withQueryString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payable_type' => ['required', Rule::in(array_keys(PaymentService::TYPES))],
            'payable_id' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'proof' => ['nullable', 'file', 'max:'.max(1, app(SettingsService::class)->proofMaxKb())],
        ]);

        $payable = $this->payments->resolvePayable($data['payable_type'], $data['payable_id']);
        $payment = $this->payments->submit($payable, $request->user(), (string) $data['amount'], PaymentMethod::from($data['method']), $request->file('proof'));

        $message = $payment->status->value === 'Paid'
            ? 'The cash payment was recorded.'
            : 'Thank you. Your payment was sent for verification.';

        return back()->with('success', $message);
    }

    public function proof(Payment $payment): StreamedResponse
    {
        $this->authorize('view', $payment);
        $proof = $payment->proof;
        abort_if($proof === null || ! Storage::disk($proof->disk)->exists($proof->path), 404);

        return Storage::disk($proof->disk)->response($proof->path, $proof->original_filename ?: basename($proof->path), [
            'Content-Type' => $proof->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }
}
