<?php

namespace App\Console\Commands;

use App\Models\AnnualFee;
use App\Models\ClassFee;
use App\Models\EventRegistration;
use App\Models\Purchase;
use App\Services\AuditLogService;
use App\Services\PaymentBalanceService;
use App\Support\Money;
use Illuminate\Console\Command;

class ScoutRecalculateBalances extends Command
{
    protected $signature = 'scout:recalculate-balances {--repair : Rewrite stored balances that do not match approved payments}';

    protected $description = 'Compare stored paid amounts with approved payments';

    public function handle(PaymentBalanceService $balances, AuditLogService $audit): int
    {
        $mismatches = 0;

        foreach ([ClassFee::class, AnnualFee::class, Purchase::class, EventRegistration::class] as $class) {
            $class::query()->chunkById(200, function ($payables) use (&$mismatches, $balances, $audit): void {
                foreach ($payables as $payable) {
                    $expected = $balances->approvedTotal($payable);

                    if (Money::compare($expected, $payable->paid_amount) === 0) {
                        continue;
                    }

                    $mismatches++;
                    $this->line(class_basename($payable)." {$payable->uuid}: stored {$payable->paid_amount}, approved {$expected}");

                    if ($this->option('repair')) {
                        $before = $payable->paid_amount;
                        $balances->recalculate($payable);
                        $audit->record('balance.repaired', $payable, ['from' => $before, 'to' => $expected]);
                    }
                }
            });
        }

        $this->info($mismatches === 0 ? 'All balances match approved payments.' : "{$mismatches} mismatch(es)".($this->option('repair') ? ' repaired.' : ' found. Run with --repair to fix.'));

        return $mismatches > 0 && ! $this->option('repair') ? self::FAILURE : self::SUCCESS;
    }
}
