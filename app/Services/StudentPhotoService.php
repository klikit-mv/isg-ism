<?php

namespace App\Services;

use App\Models\Student;
use Illuminate\Http\UploadedFile;

/**
 * Student photos: Google Drive "Students" folder when configured, else public disk.
 */
class StudentPhotoService
{
    public function __construct(private GoogleDrivePhotoService $photos, private AuditLogService $audit) {}

    public function assign(Student $student, UploadedFile $file): void
    {
        $this->photos->assignStudentPhoto($student, $file);
        $this->audit->record('student.photo_updated', $student);
    }

    public function clear(Student $student): void
    {
        $this->photos->clearStudentPhoto($student);
        $this->audit->record('student.photo_removed', $student);
    }
}
