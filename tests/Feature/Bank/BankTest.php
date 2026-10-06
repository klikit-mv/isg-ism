<?php

namespace Tests\Feature\Bank;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankTest extends TestCase
{
    use RefreshDatabase;

    private function account(string $opening = '100.00'): BankAccount
    {
        return BankAccount::query()->create([
            'name' => 'General', 'bank_name' => 'BML', 'account_number' => '123', 'opening_balance' => $opening, 'status' => 'Active',
        ]);
    }

    private function deposit(User $user, BankAccount $account, string $amount = '50.00')
    {
        return $this->actingAs($user)->post(route('bank.record', [$account, 'deposit']), [
            'amount' => $amount, 'date' => now()->toDateString(), 'party' => 'Annual fees', 'attachment' => UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf'),
        ]);
    }

    private function spend(User $user, BankAccount $account, string $amount)
    {
        return $this->actingAs($user)->post(route('bank.record', [$account, 'expense']), [
            'amount' => $amount, 'date' => now()->toDateString(), 'party' => 'Leader A', 'purpose' => 'Camp food',
        ]);
    }

    public function test_only_admins_and_leaders_can_open_the_bank_module(): void
    {
        $this->actingAs($this->admin())->get(route('bank.index'))->assertOk();
        $this->actingAs($this->leader())->get(route('bank.index'))->assertOk();
        $this->actingAs(User::factory()->parentRole()->create())->get(route('bank.index'))->assertForbidden();
        $this->actingAs($this->studentUser())->get(route('bank.index'))->assertForbidden();
    }

    public function test_deposits_add_and_spending_is_deducted_from_the_balance(): void
    {
        Storage::fake('local');
        $account = $this->account('100.00');
        $leader = $this->leader();

        $this->deposit($leader, $account, '50.00')->assertRedirect();
        $this->spend($leader, $account, '30.00')->assertSessionHas('success');

        $account->refresh();
        $this->assertSame('120.00', $account->balance());
        $this->assertSame('50.00', $account->totalDeposits());
        $this->assertSame('30.00', $account->totalExpenses());
        $this->actingAs($leader)->get(route('bank.show', $account))->assertOk()->assertSee('Camp food')->assertSee('Annual fees');
    }

    public function test_a_deposit_needs_a_slip(): void
    {
        $account = $this->account();

        $this->actingAs($this->admin())->post(route('bank.record', [$account, 'deposit']), [
            'amount' => '10', 'date' => now()->toDateString(), 'party' => 'Parents',
        ])->assertSessionHasErrors('attachment');
        $this->assertSame(0, BankTransaction::query()->count());
    }

    public function test_spending_more_than_the_balance_is_refused(): void
    {
        $account = $this->account('20.00');

        $this->spend($this->admin(), $account, '20.01')->assertSessionHas('error');
        $this->assertSame(0, BankTransaction::query()->count());
        $this->assertSame('20.00', $account->balance());
    }

    public function test_the_slip_is_stored_privately_and_can_be_opened(): void
    {
        Storage::fake('local');
        $account = $this->account();
        $admin = $this->admin();
        $this->deposit($admin, $account);

        $entry = BankTransaction::query()->firstOrFail();
        $this->assertSame('local', $entry->attachment_disk);
        Storage::disk('local')->assertExists($entry->attachment_path);
        $this->actingAs($admin)->get(route('bank.attachment', [$account, $entry]))->assertOk();
        $this->actingAs(User::factory()->parentRole()->create())->get(route('bank.attachment', [$account, $entry]))->assertForbidden();
    }

    public function test_the_slip_is_filed_in_the_drive_bank_folder_when_drive_is_set_up(): void
    {
        Storage::fake('local');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        config(['services.google.service_account_json' => json_encode(['client_email' => 'bot@example.iam.gserviceaccount.com', 'private_key' => $pem])]);
        Cache::put('google.access_token.'.md5('bot@example.iam.gserviceaccount.com'), 'token', 600);
        app(SettingsService::class)->set('google_drive_payments_folder_id', 'payroot');
        app(SettingsService::class)->set('google_drive_payments_bank_deposit', 'bankfolder');
        Http::fake(['www.googleapis.com/upload/*' => Http::response(['id' => 'slip1'])]);

        $this->deposit($this->admin(), $this->account());

        $entry = BankTransaction::query()->firstOrFail();
        $this->assertSame('drive', $entry->attachment_disk);
        $this->assertSame('drive:slip1', $entry->attachment_path);
        Http::assertSent(fn ($r) => str_contains($r->body(), '"parents":["bankfolder"]'));
        $this->assertSame([], Storage::disk('local')->allFiles('bank'));
    }

    public function test_only_admins_can_delete_and_a_needed_deposit_cannot_be_removed(): void
    {
        Storage::fake('local');
        $account = $this->account('0.00');
        $admin = $this->admin();
        $leader = $this->leader();
        $this->deposit($admin, $account, '50.00');
        $this->spend($admin, $account, '40.00');
        $deposit = BankTransaction::query()->where('type', 'deposit')->firstOrFail();
        $expense = BankTransaction::query()->where('type', 'expense')->firstOrFail();

        $this->actingAs($leader)->delete(route('bank.transactions.destroy', [$account, $expense]))->assertForbidden();
        $this->actingAs($admin)->delete(route('bank.transactions.destroy', [$account, $deposit]))->assertSessionHas('error');
        $this->assertSame(2, BankTransaction::query()->count());

        $this->actingAs($admin)->delete(route('bank.transactions.destroy', [$account, $expense]))->assertSessionHas('success');
        $this->assertSame('50.00', $account->balance());
    }

    public function test_an_inactive_account_takes_no_entries(): void
    {
        $account = $this->account();
        $account->update(['status' => 'Inactive']);

        $this->spend($this->admin(), $account, '5.00')->assertSessionHas('error');
        $this->assertSame(0, BankTransaction::query()->count());
    }

    public function test_verified_online_payments_are_added_to_the_account_that_receives_them_and_shown_separately(): void
    {
        $account = $this->account('100.00');
        $account->update(['receives_online' => true, 'online_from' => now()->subDay()->toDateString()]);
        $make = fn (string $method, string $status, string $amount, $verifiedAt) => Payment::query()->forceCreate([
            'uuid' => (string) Str::uuid(), 'payable_type' => 'class_fee', 'payable_id' => 1, 'amount' => $amount,
            'method' => $method, 'status' => $status, 'submitted_at' => now(), 'verified_at' => $verifiedAt,
        ]);
        $make('online', 'Paid', '40.00', now());
        $make('online', 'AwaitingVerification', '15.00', null);
        $make('cash', 'Paid', '25.00', now());
        $make('online', 'Paid', '9.00', now()->subDays(5));

        $this->assertSame('40.00', $account->totalOnline());
        $this->assertSame('140.00', $account->balance());
        $this->actingAs($this->admin())->get(route('bank.show', $account))->assertOk()->assertSee('Online payments (verified)')->assertSee('Deposit slips');
    }

    public function test_only_one_account_receives_online_payments(): void
    {
        $first = $this->account();
        $second = $this->account();
        $admin = $this->admin();
        $payload = fn (string $on) => ['name' => 'A', 'bank_name' => 'B', 'account_number' => '1', 'opening_balance' => '0', 'status' => 'Active', 'receives_online' => $on];

        $this->actingAs($admin)->put(route('bank.update', $first), $payload('1'));
        $this->actingAs($admin)->put(route('bank.update', $second), $payload('1'));

        $this->assertFalse($first->fresh()->receives_online);
        $this->assertTrue($second->fresh()->receives_online);
    }
}
