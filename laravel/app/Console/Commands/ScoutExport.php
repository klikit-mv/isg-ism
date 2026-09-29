<?php

namespace App\Console\Commands;

use App\Services\XlsxExportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scout:export {type : students, users, groups, activities, attendance, rover-attendance, class-fees, annual-fees, payments, payment-proofs, shop-items, purchases or audit-logs} {--path= : Output file} {--format=xlsx : xlsx or csv}')]
#[Description('Export a ledger to XLSX or CSV')]
class ScoutExport extends Command
{
    public function handle(XlsxExportService $exporter): int
    {
        $type = (string) $this->argument('type');

        if (! in_array($type, XlsxExportService::LEDGERS, true)) {
            $this->error('Unknown ledger. Choose one of: '.implode(', ', XlsxExportService::LEDGERS));

            return self::FAILURE;
        }

        $format = $this->option('format') === 'csv' ? 'csv' : 'xlsx';
        $path = $exporter->export($type, $format, $this->option('path') ?: null);
        $this->info("Exported {$type} to {$path}");

        return self::SUCCESS;
    }
}
