<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds sample import workbooks: bold headers, example rows and Excel
 * dropdown lists (backed by a hidden "Lists" sheet) for fixed-choice columns.
 */
final class TemplateBuilder
{
    public const ROWS = 500;

    private Spreadsheet $spreadsheet;

    private Worksheet $lists;

    private int $nextListColumn = 1;

    /** @var array<string, string> list key => range formula */
    private array $ranges = [];

    public function __construct()
    {
        $this->spreadsheet = new Spreadsheet;
        $this->spreadsheet->removeSheetByIndex(0);
        $this->lists = $this->spreadsheet->createSheet();
        $this->lists->setTitle('Lists');
        $this->lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
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
                $this->dropdown($sheet, $column, $header, $dropdowns[$header]);
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

    /**
     * @param  list<string>  $values
     */
    private function dropdown(Worksheet $sheet, string $column, string $header, array $values): void
    {
        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle('Choose from the list')
            ->setError('Please pick one of the values in the dropdown.')
            ->setPromptTitle($header)
            ->setPrompt('Pick a value from the list.')
            ->setFormula1($this->rangeFor($header, $values));

        $sheet->setDataValidation("{$column}2:{$column}".self::ROWS, $validation);
    }

    /**
     * @param  list<string>  $values
     */
    private function rangeFor(string $key, array $values): string
    {
        $signature = $key.'|'.implode('|', $values);

        if (isset($this->ranges[$signature])) {
            return $this->ranges[$signature];
        }

        $column = Coordinate::stringFromColumnIndex($this->nextListColumn++);
        $this->lists->setCellValue("{$column}1", $key);

        foreach (array_values($values) as $row => $value) {
            $this->lists->setCellValueExplicit($column.($row + 2), $value, DataType::TYPE_STRING);
        }

        $last = count($values) + 1;

        return $this->ranges[$signature] = "Lists!\${$column}\$2:\${$column}\${$last}";
    }
}
