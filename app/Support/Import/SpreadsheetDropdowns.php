<?php

namespace App\Support\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Adds Excel dropdown lists to a workbook. The allowed values live on a
 * hidden "Lists" sheet so long lists work and are shared between sheets.
 */
final class SpreadsheetDropdowns
{
    public const TITLE = 'Lists';

    private Worksheet $lists;

    private int $nextListColumn = 1;

    /** @var array<string, string> signature => range formula */
    private array $ranges = [];

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->lists = $spreadsheet->createSheet();
        $this->lists->setTitle(self::TITLE);
        $this->lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);
    }

    /**
     * @param  list<string>  $values
     */
    public function apply(Worksheet $sheet, string $column, int $fromRow, int $toRow, string $title, array $values): void
    {
        $values = array_values(array_unique(array_filter(array_map('strval', $values), fn (string $v) => $v !== '')));

        if ($values === []) {
            return;
        }

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowErrorMessage(true)
            ->setShowInputMessage(true)
            ->setErrorTitle('Choose from the list')
            ->setError('Please pick one of the values in the dropdown.')
            ->setPromptTitle(mb_substr($title, 0, 32))
            ->setPrompt('Pick a value from the list.')
            ->setFormula1($this->rangeFor($title, $values));

        $sheet->setDataValidation("{$column}{$fromRow}:{$column}{$toRow}", $validation);
    }

    /**
     * @param  list<string>  $values
     */
    private function rangeFor(string $key, array $values): string
    {
        $signature = implode('|', $values);

        if (isset($this->ranges[$signature])) {
            return $this->ranges[$signature];
        }

        $column = Coordinate::stringFromColumnIndex($this->nextListColumn++);
        $this->lists->setCellValue("{$column}1", $key);

        foreach ($values as $row => $value) {
            $this->lists->setCellValueExplicit($column.($row + 2), $value, DataType::TYPE_STRING);
        }

        $last = count($values) + 1;

        return $this->ranges[$signature] = self::TITLE."!\${$column}\$2:\${$column}\${$last}";
    }
}
