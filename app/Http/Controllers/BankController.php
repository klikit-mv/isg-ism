<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Services\BankService;
use App\Support\Sort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BankController extends Controller
{
    public function __construct(private BankService $bank) {}

    public function index(Request $request): View|RedirectResponse
    {
        $this->allow($request);

        $account = BankAccount::query()->orderBy('id')->first();

        return $account ? redirect()->route('bank.show', $account) : view('bank.index');
    }

    public function show(Request $request, BankAccount $account): View
    {
        $this->allow($request);

        $transactions = $account->transactions()
            ->with('recorder')
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('from'), fn ($q, $date) => $q->whereDate('transaction_date', '>=', $date))
            ->when($request->query('to'), fn ($q, $date) => $q->whereDate('transaction_date', '<=', $date))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('party', 'like', "%{$term}%")
                ->orWhere('purpose', 'like', "%{$term}%")
                ->orWhere('details', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")))
            ->tap(fn ($q) => Sort::apply($q, $request, [
                'date' => 'transaction_date', 'type' => 'type', 'party' => 'party', 'purpose' => 'purpose', 'amount' => 'amount',
                'recorder' => fn ($q, $dir) => $q->orderBy(DB::table('users')->select('name')->whereColumn('users.id', 'bank_transactions.recorded_by'), $dir),
            ], 'date', 'desc', 'id'))
            ->paginate(25)->withQueryString();

        $online = $account->receives_online
            ? $account->onlinePayments()->with('student', 'submitter')->orderByDesc('verified_at')->paginate(15, ['*'], 'online_page')->withQueryString()
            : null;

        return view('bank.show', ['account' => $account, 'transactions' => $transactions, 'online' => $online]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow($request);
        $account = $this->bank->createAccount($this->accountData($request), $request->user());

        return redirect()->route('bank.show', $account)->with('success', 'The bank account was added.');
    }

    public function update(Request $request, BankAccount $account): RedirectResponse
    {
        $this->allow($request);
        $this->bank->updateAccount($account, $this->accountData($request) + ['status' => $request->validate(['status' => ['required', Rule::in(['Active', 'Inactive'])]])['status']], $request->user());

        return back()->with('success', 'The account was updated.');
    }

    public function record(Request $request, BankAccount $account, string $type): RedirectResponse
    {
        $this->allow($request);
        abort_unless(in_array($type, [BankTransaction::DEPOSIT, BankTransaction::EXPENSE], true), 404);
        $deposit = $type === BankTransaction::DEPOSIT;

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'party' => ['required', 'string', 'max:255'],
            'purpose' => [$deposit ? 'nullable' : 'required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'reference' => ['nullable', 'string', 'max:255'],
            'attachment' => [$deposit ? 'required' : 'nullable', 'file'],
        ]);

        $this->bank->record($account, $type, $data, $request->file('attachment'), $request->user());

        return back()->with('success', $deposit ? 'The deposit was recorded.' : 'The spending was recorded and deducted from the balance.');
    }

    public function destroy(Request $request, BankAccount $account, BankTransaction $transaction): RedirectResponse
    {
        abort_unless($request->user()->isAdmin() && $request->user()->isActive(), 403);
        abort_unless($transaction->bank_account_id === $account->id, 404);
        $this->bank->delete($transaction, $request->user());

        return back()->with('success', 'The entry was removed.');
    }

    public function attachment(Request $request, BankAccount $account, BankTransaction $transaction): Response
    {
        $this->allow($request);
        abort_unless($transaction->bank_account_id === $account->id, 404);
        $contents = $this->bank->attachment($transaction);
        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => $transaction->attachment_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($transaction->attachment_name ?: 'attachment').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function allow(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && $user->isActive() && ($user->isAdmin() || $user->isLeader()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function accountData(Request $request): array
    {
        return $request->validate([
            'online_from' => ['nullable', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:64'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
