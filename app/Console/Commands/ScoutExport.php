<?php

namespace App\Console\Commands;

use App\Services\XlsxExportService;
use Illuminate\Console\Command;

class ScoutExport extends Command
{
    protected $signature = 'scout:export {type : students, users, groups, activities, attendance, rover-attendance, class-fees, annual-fees, payments, payment-proofs, shop-items, purchases or audit-logs} {--path= : Output file} {--format=xlsx : xlsx or csv}';

    protected $description = 'Export a ledger to XLSX or CSV';

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
