<?php

namespace App\Services;

use App\Exceptions\ScoutException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Certificates made by other parties (for example SAM), uploaded as PDFs, many at a time. Files are held privately while
 * the leader checks which scout each one belongs to, then stored with the scout's other certificates.
 */
class ExternalCertificateService
{
    public const DISK = 'local';

    public const MAX_FILES = 50;

    public function __construct(private CertificateGenerationService $generator, private LeaderScopeService $scope, private SettingsService $settings) {}

    /**
     * Hold the uploaded PDFs and guess the scout for each from its file name (National ID, index number or name).
     *
     * @param  list<UploadedFile>  $files
     * @return array{token: string, rows: list<array{key: string, file: string, title: string, student_id: ?int}>}
     */
    public function stage(array $files, User $actor): array
    {
        $token = (string) Str::uuid();
        $students = $this->scope->constrainStudents(Student::query(), $actor)->get(['id', 'name', 'national_id', 'index_number']);
        $rows = [];

        foreach ($files as $index => $file) {
            $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);

            if (! $file->isValid() || ! str_starts_with($head, '%PDF') || $file->getSize() > $this->settings->proofMaxBytes() * 4) {
                throw new ScoutException('"'.$file->getClientOriginalName().'" is not a PDF or is too large.');
            }

            $key = 'f'.$index;
            Storage::disk(self::DISK)->putFileAs('external-staging/'.$token, $file, $key.'.pdf');
            $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

            $rows[] = [
                'key' => $key,
                'file' => mb_substr($file->getClientOriginalName(), 0, 120),
                'title' => Str::limit(trim((string) preg_replace('/[_\s]+/', ' ', $base)), 120, ''),
                'student_id' => $this->match($base, $students)?->id,
            ];
        }

        return ['token' => $token, 'rows' => $rows];
    }

    /**
     * @param  iterable<Student>  $students
     */
    public function match(string $fileName, iterable $students): ?Student
    {
        $haystack = Str::lower($fileName);
        $squashed = preg_replace('/[^a-z0-9]/', '', $haystack);

        foreach (['national_id', 'index_number'] as $field) {
            foreach ($students as $student) {
                $value = Str::lower(trim((string) $student->{$field}));

                if (strlen($value) >= 4 && str_contains($squashed, preg_replace('/[^a-z0-9]/', '', $value))) {
                    return $student;
                }
            }
        }

        foreach ($students as $student) {
            $name = preg_replace('/[^a-z0-9]/', '', Str::lower($student->name));

            if (strlen($name) >= 5 && str_contains($squashed, $name)) {
                return $student;
            }
        }

        return null;
    }

    /**
     * Store the checked rows. Each is separate: a bad one is reported and never undoes the others.
     *
     * @param  list<array{key: string, student_id: ?int, title: ?string, skip?: mixed}>  $rows
     * @return array{created: int, failed: array<string, string>}
     */
    public function commit(string $token, array $rows, ?string $issuer, string $date, User $actor): array
    {
        abort_unless(Str::isUuid($token), 404);

        $created = 0;
        $failed = [];

        foreach ($rows as $row) {
            if (! empty($row['skip'])) {
                continue;
            }

            $path = 'external-staging/'.$token.'/'.preg_replace('/[^a-z0-9]/i', '', (string) $row['key']).'.pdf';
            $label = (string) ($row['title'] ?? $row['key']);

            try {
                $student = isset($row['student_id']) ? Student::query()->find($row['student_id']) : null;

                if ($student === null) {
                    throw new ScoutException('No scout chosen.');
                }

                if (! $this->scope->canAccessStudent($actor, $student)) {
                    throw new ScoutException('Not one of your scouts.');
                }

                $pdf = Storage::disk(self::DISK)->get($path);

                if ($pdf === null) {
                    throw new ScoutException('The file is no longer held. Upload it again.');
                }

                $this->generator->storeExternalCertificate($student, (string) ($row['title'] ?? ''), $issuer, $date, $pdf, $actor);
                $created++;
            } catch (Throwable $e) {
                $failed[$label] = $e->getMessage();
            }
        }

        Storage::disk(self::DISK)->deleteDirectory('external-staging/'.$token);

        return ['created' => $created, 'failed' => $failed];
    }
}
