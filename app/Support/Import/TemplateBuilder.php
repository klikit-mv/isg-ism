<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds sample import workbooks: bold headers, example rows and Excel
 * dropdown lists for fixed-choice columns.
 */
final class TemplateBuilder
{
    public const ROWS = 500;

    private Spreadsheet $spreadsheet;

    private SpreadsheetDropdowns $dropdowns;

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);
        $this->dropdowns = new SpreadsheetDropdowns($this->spreadsheet);
    }

    /**
     * Add a sheet with headers, example rows and dropdowns.
     *
     * @param  list<string>  $headers
     * @param  list<list<string|int|float>>  $examples
     * @param  array<string, list<string>>  $dropdowns  header => allowed values
     */
    public function sheet(string $title, array $headers, array $examples = [], array $dropdowns = []): self
    {
        $sheet = $this->spreadsheet->createSheet($this->spreadsheet->getSheetCount() - 1);
        $sheet->setTitle($title);
        $sheet->fromArray(array_merge([$headers], $examples), null, 'A1', true);

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()->setFillType('solid')->getStartColor()->setRGB('F3E8FF');
        $sheet->freezePane('A2');

        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($column)->setAutoSize(true);

            if (isset($dropdowns[$header])) {
                $this->dropdowns->apply($sheet, $column, 2, self::ROWS, $header, $dropdowns[$header]);
            }
        }

        return $this;
    }

    public function save(): string
    {
        $this->spreadsheet->setActiveSheetIndex(0);
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($this->spreadsheet))->save($path);
        $this->spreadsheet->disconnectWorksheets();

        return $path;
    }
}
