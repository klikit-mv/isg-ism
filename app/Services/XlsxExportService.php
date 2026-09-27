<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
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

        return $this->write($rows, $format, $type);
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

        return $this->write($rows, $format, $type, $path);
    }

    /**
     * @return array{0: list<string>, 1: Builder}
     */
    private function ledger(string $type): array
    {
        $columns = match ($type) {
            'students' => ['students', ['uuid', 'index_number', 'name', 'national_id', 'email', 'gender', 'date_of_birth', 'section', 'class_name', 'patrol', 'status', 'parent_name', 'primary_mobile', 'secondary_mobile', 'permanent_address', 'present_address', 'created_at']],
            'users' => ['users', ['uuid', 'name', 'national_id', 'email', 'status', 'last_login_at', 'created_at']],
            'groups' => ['groups', ['uuid', 'name', 'type', 'status', 'created_at']],
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
     */
    private function write(array $rows, string $format, string $name, ?string $path = null): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit(Str::studly($name), 28, ''));
        $sheet->fromArray($rows, null, 'A1', true);

        $path ??= storage_path('app/private/exports/'.$name.'-'.now()->format('Ymd-His').'-'.Str::random(8).'.'.$format);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        $writer = $format === 'csv' ? new Csv($spreadsheet) : new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }
}
