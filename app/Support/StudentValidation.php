<?php

namespace App\Support;

use App\Enums\Gender;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Validation\Rule;

/**
 * Shared scout validation for public registration, enrolment, edits and import.
 */
final class StudentValidation
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?Student $student = null, bool $publicRegistration = false): array
    {
        $userId = $student?->user?->id;

        $rules = [
            'index_number' => ['required', 'string', 'max:50', Rule::unique('students', 'index_number')->ignore($student?->id)],
            'national_id' => ['required', 'string', 'max:64', Rule::unique('students', 'national_id')->ignore($student?->id), Rule::unique('users', 'national_id')->ignore($userId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student?->id), Rule::unique('users', 'email')->ignore($userId)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'permanent_address' => ['required', 'string', 'max:255'],
            'present_address' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'parent_name' => ['required', 'string', 'max:255'],
            'primary_mobile' => ['required', 'string', 'max:30'],
            'secondary_mobile' => ['nullable', 'string', 'max:30'],
            'section' => ['required', Rule::enum(ScoutSection::class)],
        ];

        if ($publicRegistration) {
            $rules['pin'] = ['required', 'string', 'min:4', 'max:32', 'confirmed'];

            return $rules;
        }

        $rules['status'] = ['required', Rule::enum(StudentStatus::class)];

        if ($student === null) {
            $rules['pin'] = ['nullable', 'string', 'min:4', 'max:32'];
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public static function prepare(array $input): array
    {
        if (isset($input['national_id'])) {
            $input['national_id'] = strtoupper(trim((string) $input['national_id']));
        }

        return $input;
    }
}
