<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use App\Support\Import\SpreadsheetReader;
use App\Support\StudentValidation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Excel enrolment: template → preview (dry run) → confirm.
 */
class StudentImportService
{
    public const HEADERS = [
        'name', 'national_id', 'email', 'gender', 'section', 'index_number', 'permanent_address', 'present_address',
        'date_of_birth', 'parent_name', 'primary_mobile', 'secondary_mobile', 'class_name', 'patrol', 'status', 'pin',
    ];

    public const REQUIRED = ['name', 'national_id', 'email'];

    public function __construct(private StudentService $students, private AuditLogService $audit) {}

    public function template(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Students');
        $sheet->fromArray([
            self::HEADERS,
            ['Aishath Example', 'A123456', 'aishath@example.com', 'Female', 'Cub Scout', 'IX1001', 'Henveiru, Male', 'Henveiru, Male', '15.03.2015', 'Ibrahim Example', '7771234', '', 'Grade 4', 'Eagle', 'active', ''],
        ]);

        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    /**
     * @return array{rows: list<array{row: int, name: string, national_id: string, result: string, message: string}>, created: int, skipped: int, errors: int, missing: list<string>}
     */
    public function preview(string $path): array
    {
        return $this->process($path, null);
    }

    /**
     * @return array{rows: list<array{row: int, name: string, national_id: string, result: string, message: string}>, created: int, skipped: int, errors: int, missing: list<string>}
     */
    public function import(string $path, User $actor): array
    {
        return DB::transaction(function () use ($path, $actor): array {
            $report = $this->process($path, $actor);
            $this->audit->record('student.imported', null, ['created' => $report['created'], 'skipped' => $report['skipped'], 'errors' => $report['errors']], $actor);

            return $report;
        });
    }

    /**
     * @return array{rows: list<array{row: int, name: string, national_id: string, result: string, message: string}>, created: int, skipped: int, errors: int, missing: list<string>}
     */
    private function process(string $path, ?User $actor): array
    {
        $sheets = SpreadsheetReader::read($path);
        $sheet = reset($sheets) ?: ['headers' => [], 'rows' => []];
        $normalizedRequired = array_map([SpreadsheetReader::class, 'normalizeHeader'], self::REQUIRED);
        $missing = array_values(array_diff($normalizedRequired, $sheet['headers']));
        $report = ['rows' => [], 'created' => 0, 'skipped' => 0, 'errors' => 0, 'missing' => $missing];

        if ($missing !== []) {
            return $report;
        }

        $emailCounts = array_count_values(array_filter(array_map(fn ($r) => strtolower($r['email'] ?? ''), $sheet['rows'])));

        foreach ($sheet['rows'] as $index => $raw) {
            $data = $this->normalizeRow($raw);
            $line = ['row' => $index + 2, 'name' => $data['name'] ?? '', 'national_id' => $data['national_id'] ?? ''];

            if (($emailCounts[strtolower($data['email'] ?? '')] ?? 0) > 1) {
                $report['rows'][] = $line + ['result' => 'error', 'message' => 'This email appears more than once in the file.'];
                $report['errors']++;

                continue;
            }

            if (filled($data['national_id'] ?? null) && (Student::withTrashed()->where('national_id', $data['national_id'])->exists() || User::withTrashed()->where('national_id', $data['national_id'])->exists())) {
                $report['rows'][] = $line + ['result' => 'exists', 'message' => 'A scout or user with this National ID already exists.'];
                $report['skipped']++;

                continue;
            }

            $validator = Validator::make($data, StudentValidation::rules());

            if ($validator->fails()) {
                $report['rows'][] = $line + ['result' => 'error', 'message' => implode(' ', $validator->errors()->all())];
                $report['errors']++;

                continue;
            }

            if ($actor !== null) {
                $this->students->create($validator->validated(), $actor);
                $report['created']++;
                $report['rows'][] = $line + ['result' => 'created', 'message' => 'Enrolled.'];
            } else {
                $report['rows'][] = $line + ['result' => 'ready', 'message' => 'Ready to import.'];
            }
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $raw
     * @return array<string, mixed>
     */
    private function normalizeRow(array $raw): array
    {
        $data = [];

        foreach (self::HEADERS as $header) {
            $value = $raw[SpreadsheetReader::normalizeHeader($header)] ?? null;
            $data[$header] = $value === '' ? null : $value;
        }

        $data['national_id'] = isset($data['national_id']) ? strtoupper(trim((string) $data['national_id'])) : null;
        $data['gender'] = Gender::fromLoose($data['gender'])?->value ?? $data['gender'];
        $data['section'] = ScoutSection::fromLoose($data['section'])?->value ?? $data['section'];
        $data['status'] = StudentStatus::fromLoose($data['status'])?->value ?? ($data['status'] ?? StudentStatus::Active->value);
        $data['date_of_birth'] = SpreadsheetReader::parseDate($data['date_of_birth'])?->toDateString() ?? $data['date_of_birth'];

        return $data;
    }
}
