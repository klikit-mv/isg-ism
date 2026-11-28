<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadershipRecord extends Model
{
    use HasUuid;

    protected $fillable = [
        'student_id', 'post', 'patrol_or_six', 'troop_or_group', 'start_date', 'end_date',
        'certificate_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    /**
     * What must still be filled in before a certificate can be generated.
     *
     * @return list<string>
     */
    public function missingForCertificate(): array
    {
        return array_keys(array_filter([
            'Scout' => $this->student_id === null,
            'Post' => blank($this->post),
            'Patrol or six' => blank($this->patrol_or_six),
            'Start date' => $this->start_date === null,
        ]));
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Certificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class);
    }
}
