<?php

namespace App\Services;

use App\Exceptions\ScoutException;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\User;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The group's bank accounts: deposits (with slips) add to the balance, requested spending takes from it.
 */
class BankService
{
    public const DISK = 'local';

    public function __construct(private AuditLogService $audit, private SettingsService $settings, private GoogleDrivePaymentService $drive) {}

    /**
     * @param  array{name: string, bank_name: string, account_name?: ?string, account_number: string, opening_balance: string|int|float|null, notes?: ?string, status?: string}  $data
     */
    public function createAccount(array $data, User $actor): BankAccount
    {
        if (BankAccount::query()->exists()) {
            throw new ScoutException('The group has one bank account. Edit it instead of adding another.');
        }

        $account = BankAccount::query()->create([
            'name' => $data['name'],
            'bank_name' => $data['bank_name'],
            'account_name' => $data['account_name'] ?? null,
            'account_number' => $data['account_number'],
            'opening_balance' => Money::normalize($data['opening_balance'] ?? 0),
            'notes' => $data['notes'] ?? null,
            'status' => 'Active',
            'created_by' => $actor->id,
            'receives_online' => true,
            'online_from' => $data['online_from'] ?? now()->toDateString(),
        ]);
        $this->audit->record('bank_account.created', $account, ['name' => $account->name, 'opening_balance' => $account->opening_balance], $actor);

        return $account;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateAccount(BankAccount $account, array $data, User $actor): void
    {
        $opening = Money::normalize($data['opening_balance'] ?? 0);

        DB::transaction(function () use ($account, $data, $opening, $actor): void {
            $locked = BankAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $locked->online_from = $data['online_from'] ?? $locked->online_from;
            $after = Money::sub(Money::add(Money::add($opening, $locked->totalDeposits()), $locked->totalOnline()), $locked->totalExpenses());

            if (Money::compare($after, '0') < 0) {
                throw new ScoutException('That opening balance would make the account balance negative, because spending already recorded is higher.');
            }

            $locked->update([
                'name' => $data['name'],
                'bank_name' => $data['bank_name'],
                'account_name' => $data['account_name'] ?? null,
                'account_number' => $data['account_number'],
                'opening_balance' => $opening,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? $locked->status,
                'online_from' => $locked->online_from,
            ]);
            $this->audit->record('bank_account.updated', $locked, ['name' => $locked->name, 'opening_balance' => $opening], $actor);
        });
    }

    /**
     * Record a deposit or an expense. Spending can never take the account below zero.
     *
     * @param  array{amount: string|int|float, date: string, party: string, purpose?: ?string, details?: ?string, reference?: ?string}  $data
     */
    public function record(BankAccount $account, string $type, array $data, ?UploadedFile $attachment, User $actor): BankTransaction
    {
        $amount = Money::normalize($data['amount']);

        if (! Money::isPositive($amount)) {
            throw new ScoutException('Enter an amount greater than zero.');
        }

        $attachmentData = $attachment ? $this->storeAttachment($attachment, $type, $actor) : [];

        try {
            return DB::transaction(function () use ($account, $type, $data, $amount, $attachmentData, $actor): BankTransaction {
                $locked = BankAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();

                if ($locked->status !== 'Active') {
                    throw new ScoutException('This account is inactive. Make it active to record entries.');
                }

                if ($type === BankTransaction::EXPENSE && Money::compare($amount, $locked->balance()) > 0) {
                    throw new ScoutException('The account only has '.scout_money($locked->balance()).', which is less than this spending.');
                }

                $transaction = $locked->transactions()->create([
                    'type' => $type,
                    'amount' => $amount,
                    'transaction_date' => $data['date'],
                    'party' => $data['party'],
                    'purpose' => $data['purpose'] ?? null,
                    'details' => $data['details'] ?? null,
                    'reference' => $data['reference'] ?? null,
                    'recorded_by' => $actor->id,
                ] + $attachmentData);

                $this->audit->record("bank.{$type}_recorded", $locked, ['amount' => $amount, 'party' => $data['party'], 'purpose' => $data['purpose'] ?? null, 'balance' => $locked->balance()], $actor);

                return $transaction;
            });
        } catch (\Throwable $e) {
            $this->discard($attachmentData);

            throw $e;
        }
    }

    /**
     * Deleting an entry (admins only) is refused when it would leave the balance negative.
     */
    public function delete(BankTransaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($transaction, $actor): void {
            $account = BankAccount::query()->whereKey($transaction->bank_account_id)->lockForUpdate()->firstOrFail();

            if ($transaction->isDeposit() && Money::compare(Money::sub($account->balance(), $transaction->amount), '0') < 0) {
                throw new ScoutException('This deposit cannot be removed: spending recorded after it would take the balance below zero.');
            }

            $this->audit->record('bank.'.$transaction->type.'_deleted', $account, ['amount' => $transaction->amount, 'party' => $transaction->party, 'purpose' => $transaction->purpose], $actor);
            $this->discard($transaction->only(['attachment_disk', 'attachment_path']));
            $transaction->delete();
        });
    }

    public function attachment(BankTransaction $transaction): ?string
    {
        if ($transaction->attachment_path === null) {
            return null;
        }

        if ($transaction->attachment_disk === 'drive') {
            return $this->drive->contents($transaction->attachment_path);
        }

        return Storage::disk((string) $transaction->attachment_disk)->exists($transaction->attachment_path)
            ? Storage::disk((string) $transaction->attachment_disk)->get($transaction->attachment_path)
            : null;
    }

    /**
     * Slip or receipt: a PDF or an image. It goes to the Drive payments folder (Bank deposits / Bank expenses) when
     * Drive is set up, otherwise to the private disk, and a warning says why when Drive refused it.
     *
     * @return array<string, ?string>
     */
    private function storeAttachment(UploadedFile $file, string $type, User $actor): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: (string) $file->extension());

        if (! $file->isValid() || ! in_array($extension, ['pdf', 'jpg', 'jpeg', 'png', 'webp'], true) || $file->getSize() > $this->settings->proofMaxBytes()) {
            throw new ScoutException('The attachment must be a PDF, JPG, PNG or WEBP file within the size limit ('.round($this->settings->proofMaxKb() / 1024, 1).' MB).');
        }

        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $name = mb_substr($file->getClientOriginalName(), 0, 255);
        $ref = $this->drive->put('bank_'.$type, now()->format('Y-m-d').' '.Str::limit(pathinfo($name, PATHINFO_FILENAME), 60, '').' '.Str::random(5).'.'.$extension, (string) $file->get(), $mime, $actor->name);

        if ($ref !== null) {
            return ['attachment_disk' => 'drive', 'attachment_path' => $ref, 'attachment_name' => $name, 'attachment_mime' => $mime];
        }

        if ($this->drive->enabled()) {
            session()->flash('warning', 'The file was saved on the server, not in Google Drive. '.$this->drive->lastError());
        }

        $path = Uploads::store($file, 'bank/'.$type, Str::uuid().'.'.$extension, self::DISK);

        return ['attachment_disk' => self::DISK, 'attachment_path' => $path, 'attachment_name' => $name, 'attachment_mime' => $mime];
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function discard(array $attachment): void
    {
        $path = $attachment['attachment_path'] ?? null;

        if (! $path) {
            return;
        }

        if (($attachment['attachment_disk'] ?? null) === 'drive') {
            $this->drive->delete((string) $path);
        } else {
            Storage::disk((string) ($attachment['attachment_disk'] ?? self::DISK))->delete((string) $path);
        }
    }
}
