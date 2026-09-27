<?php

namespace App\Console\Commands;

use App\Services\LegacyImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scout:import-legacy {--file= : Path to the workbook} {--sheet= : Import only this sheet} {--dry-run : Check without saving} {--force : Save the import}')]
#[Description('Inspect and import a legacy workbook (dry run unless --force)')]
class ScoutImportLegacy extends Command
{
    public function handle(LegacyImportService $imports): int
    {
        $file = (string) $this->option('file');

        if ($file === '' || ! is_file($file)) {
            $this->error('Pass an existing workbook with --file=.');

            return self::FAILURE;
        }

        $inspection = $imports->inspect($file);
        $this->table(['Sheet', 'Rows', 'Valid', 'Invalid', 'Missing columns'], array_map(fn ($s) => [
            $s['name'].($s['known'] ? '' : ' (not imported)'), $s['rows'], $s['valid'], $s['invalid'], implode(', ', $s['missing']),
        ], $inspection['sheets']));

        if ($inspection['missing_identity'] !== []) {
            $this->warn('Missing identity sheets: '.implode(', ', $inspection['missing_identity']));
        }

        $dryRun = $this->option('dry-run') || ! $this->option('force');
        $result = $imports->import($file, $dryRun, null, $this->option('sheet') ?: null);

        $this->table(['Sheet', 'Imported', 'Errors'], array_map(fn ($sheet, $c) => [$sheet, $c['imported'], $c['errors']], array_keys($result['counts']), $result['counts']));

        foreach (array_slice($result['errors'], 0, 100) as $error) {
            $this->line("  {$error['sheet']}:{$error['row']} — {$error['message']}");
        }

        $this->info($dryRun ? 'Dry run only: nothing was saved. Add --force to import.' : 'Import saved.');

        return self::SUCCESS;
    }
}
