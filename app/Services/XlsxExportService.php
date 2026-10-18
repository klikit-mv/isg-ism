<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PersonType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Enums\RoverAttendanceStatus;
use App\Enums\ScoutSection;
use App\Enums\ShopItemStatus;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Import\SpreadsheetDropdowns;
use App\Support\Import\TemplateBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * XLSX/CSV writer for reports and ledger exports. Every export goes to a
 * unique temporary file so users never overwrite each other.
 */
class XlsxExportService
{
    public const LEDGERS = [
        'students', 'users', 'groups', 'activities', 'attendance', 'rover-attendance', 'class-fees',
        'annual-fees', 'payments', 'payment-proofs', 'shop-items', 'purchases', 'audit-logs',
    ];

    public function __construct(private ReportService $reports) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportReport(string $type, array $filters, User $user, string $format = 'xlsx'): string
    {
        $meta = $this->reports->catalog()[$type];
        $headings = $this->reports->headings($type);
        $rows = [
            [config('scout.organisation')],
            [$meta['title'].' report'],
            ['Generated '.scout_datetime(now()).' by '.$user->name],
            [],
            $headings,
        ];

        foreach ($this->reports->query($type, $filters, $user)->cursor() as $row) {
            $rows[] = $this->reports->mapRow($type, $row);
        }

        $rows[] = $this->reports->totalsRow($type, $this->reports->totals($type, $filters, $user));

        return $this->write($rows, $format, $type, null, 5, $this->reportDropdowns($type));
    }

    /**
     * Full ledger export for the scout:export command.
     */
    public function export(string $type, string $format = 'xlsx', ?string $path = null): string
    {
        [$headings, $query] = $this->ledger($type);
        $rows = [$headings];

        foreach ($query->cursor() as $row) {
            $rows[] = array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), array_values((array) $row));
        }

        return $this->write($rows, $format, $type, $path, 1, $this->ledgerDropdowns($type));
    }

    /**
     * Valid values for fixed-choice report columns, as the report prints them.
     *
     * @return array<string, list<string>> heading => values
     */
    public function reportDropdowns(string $type): array
    {
        $feeStatuses = array_values(FeeStatus::options());

        return match ($type) {
            'attendance' => ['Section' => ScoutSection::values(), 'Status' => AttendanceStatus::values()],
            'rover-attendance' => ['Status' => RoverAttendanceStatus::values(), 'Required/Optional' => ['Required', 'Optional']],
            'annual-fees' => ['Section' => ScoutSection::values(), 'Status' => $feeStatuses],
            'class-fees' => ['Status' => $feeStatuses],
            'payments' => [
                'Type' => array_map(fn (string $key) => str_replace('_', ' ', ucfirst($key)), array_keys(PaymentService::TYPES)),
                'Method' => array_values(PaymentMethod::options()),
                'Status' => array_values(PaymentStatus::options()),
            ],
            'shop' => ['Payment status' => $feeStatuses, 'Purchase status' => array_values(PurchaseStatus::options())],
            default => [],
        };
    }

    /**
     * Valid stored values for fixed-choice ledger columns.
     *
     * @return array<string, list<string>> column => values
     */
    public function ledgerDropdowns(string $type): array
    {
        $status = match ($type) {
            'students' => StudentStatus::values(),
            'users' => UserStatus::values(),
            'groups' => RecordStatus::values(),
            'attendance' => AttendanceStatus::values(),
            'rover-attendance' => RoverAttendanceStatus::values(),
            'class-fees', 'annual-fees' => FeeStatus::values(),
            'payments' => PaymentStatus::values(),
            'shop-items' => ShopItemStatus::values(),
            default => null,
        };

        return array_filter([
            'status' => $status,
            'gender' => Gender::values(),
            'section' => ScoutSection::values(),
            'method' => PaymentMethod::values(),
            'person_type' => PersonType::values(),
            'payment_status' => FeeStatus::values(),
            'purchase_status' => PurchaseStatus::values(),
            'payable_type' => array_keys(PaymentService::TYPES),
        ]);
    }

    /**
     * @return array{0: list<string>, 1: Builder}
     */
    private function ledger(string $type): array
    {
        $columns = match ($type) {
            'students' => ['students', ['uuid', 'index_number', 'name', 'national_id', 'email', 'gender', 'date_of_birth', 'section', 'class_name', 'patrol', 'status', 'parent_name', 'primary_mobile', 'secondary_mobile', 'permanent_address', 'present_address', 'created_at']],
            'users' => ['users', ['uuid', 'name', 'national_id', 'email', 'status', 'last_login_at', 'created_at']],
            'groups' => ['groups', ['uuid', 'name', 'type', 'section', 'status', 'created_at']],
            'activities' => ['activities', ['uuid', 'name', 'date', 'all_students', 'charge_fee', 'fee_amount', 'created_at']],
            'attendance' => ['attendance_records', ['uuid', 'activity_id', 'student_id', 'status', 'remarks', 'marked_by', 'marked_at']],
            'rover-attendance' => ['rover_attendance_records', ['uuid', 'activity_id', 'student_id', 'status', 'is_required', 'marked_by', 'marked_at']],
            'class-fees' => ['class_fees', ['uuid', 'activity_id', 'student_id', 'amount', 'paid_amount', 'outstanding_amount', 'status', 'due_date', 'voided_at']],
            'annual-fees' => ['annual_fees', ['uuid', 'annual_fee_year_id', 'student_id', 'user_id', 'person_type', 'section', 'amount', 'paid_amount', 'outstanding_amount', 'status']],
            'payments' => ['payments', ['uuid', 'payable_type', 'payable_id', 'student_id', 'amount', 'method', 'source', 'status', 'submitted_by', 'submitted_at', 'verified_by', 'verified_at', 'rejection_reason']],
            'payment-proofs' => ['payment_proofs', ['uuid', 'payment_id', 'disk', 'path', 'original_filename', 'mime_type', 'file_size', 'uploaded_at']],
            'shop-items' => ['shop_items', ['uuid', 'name', 'price', 'stock_qty', 'status', 'created_at']],
            'purchases' => ['purchases', ['uuid', 'student_id', 'total_amount', 'paid_amount', 'outstanding_amount', 'payment_status', 'purchase_status', 'stock_decremented', 'delivered_at', 'recipient', 'created_at']],
            'audit-logs' => ['audit_logs', ['uuid', 'action', 'entity_type', 'entity_id', 'actor_user_id', 'details', 'created_at']],
        };

        [$table, $fields] = $columns;
        $query = DB::table($table)->select($fields)->orderBy('id');

        if ($type === 'audit-logs') {
            $ids = DB::table('audit_logs')->orderByDesc('id')->limit(5000)->pluck('id');
            $query->whereIn('id', $ids->all() ?: [0]);
        }

        return [$fields, $query];
    }

    /**
     * @param  list<array<int, mixed>>  $rows
     * @param  array<string, list<string>>  $dropdowns  heading => allowed values
     */
    private function write(array $rows, string $format, string $name, ?string $path = null, int $headingRow = 1, array $dropdowns = []): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(Str::studly($name), 28, ''));
        $sheet->fromArray($rows, null, 'A1', true);

        if ($format !== 'csv' && $dropdowns !== []) {
            $this->addDropdowns($spreadsheet, $sheet, $rows[$headingRow - 1] ?? [], $headingRow, count($rows), $dropdowns);
        }

        $path ??= storage_path('app/private/exports/'.$name.'-'.now()->format('Ymd-His').'-'.Str::random(8).'.'.$format);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /**
     * Dropdowns on every data row below the headings, plus room to add rows.
     *
     * @param  array<int, mixed>  $headings
     * @param  array<string, list<string>>  $dropdowns
     */
    private function addDropdowns(Spreadsheet $spreadsheet, Worksheet $sheet, array $headings, int $headingRow, int $lastRow, array $dropdowns): void
    {
        $lists = new SpreadsheetDropdowns($spreadsheet);
        $toRow = max($lastRow, $headingRow + TemplateBuilder::ROWS);

        foreach (array_values($headings) as $index => $heading) {
            if (isset($dropdowns[(string) $heading])) {
                $lists->apply($sheet, Coordinate::stringFromColumnIndex($index + 1), $headingRow + 1, $toRow, (string) $heading, $dropdowns[(string) $heading]);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);
    }
}
