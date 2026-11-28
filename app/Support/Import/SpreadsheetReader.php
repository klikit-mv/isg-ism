<?php

namespace App\Support\Import;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads XLSX/XLS/CSV sheets into rows keyed by normalised headers.
 */
final class SpreadsheetReader
{
    /**
     * Lower-case alphanumerics only: "Student ID" and "student_id" both become "studentid".
     */
    public static function normalizeHeader(mixed $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $header));
    }

    /**
     * @return array<string, array{headers: list<string>, rows: list<array<string, string>>}>
     */
    public static function read(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheets = [];

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            if ($worksheet->getSheetState() !== Worksheet::SHEETSTATE_VISIBLE) {
                continue;
            }

            $data = $worksheet->toArray(null, true, false, false);
            $headerRow = array_shift($data) ?? [];
            $headers = array_map([self::class, 'normalizeHeader'], $headerRow);
            $rows = [];

            foreach ($data as $cells) {
                if (count(array_filter($cells, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $row = [];

                foreach ($headers as $index => $header) {
                    if ($header === '') {
                        continue;
                    }

                    $value = $cells[$index] ?? null;
                    $row[$header] = is_float($value) && floor($value) === $value ? (string) (int) $value : trim((string) $value);
                }

                $rows[] = $row;
            }

            $sheets[$worksheet->getTitle()] = ['headers' => array_values(array_filter($headers)), 'rows' => $rows];
        }

        $spreadsheet->disconnectWorksheets();

        return $sheets;
    }

    /**
     * Accepts dd.MM.yyyy[ HH:mm], ISO, d/m/Y and Excel serial numbers.
     */
    public static function parseDate(?string $value, bool $withTime = false): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        try {
            if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            foreach (['d.m.Y H:i', 'd.m.Y', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
                try {
                    $date = Carbon::createFromFormat('!'.$format, $value);
                } catch (Throwable) {
                    continue;
                }

                if ($date !== false && $date->format($format) === $value) {
                    return $date;
                }
            }

            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function truthy(?string $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'y', 'x', '✓'], true);
    }
}
