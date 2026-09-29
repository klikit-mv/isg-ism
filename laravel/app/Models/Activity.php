<?php

namespace App\Models;

use App\Enums\ScoutSection;
use App\Models\Concerns\HasUuid;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'name', 'date', 'details', 'all_students', 'charge_fee', 'fee_amount',
        'certificate_template_id', 'created_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'all_students' => 'boolean',
            'charge_fee' => 'boolean',
            'fee_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'activity_groups');
    }

    /**
     * @return list<ScoutSection>
     */
    public function sections(): array
    {
        return DB::table('activity_sections')
            ->where('activity_id', $this->id)
            ->pluck('section')
            ->map(fn ($s) => ScoutSection::tryFrom($s))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, ScoutSection|string>  $sections
     */
    public function syncSections(array $sections): void
    {
        DB::table('activity_sections')->where('activity_id', $this->id)->delete();

        $rows = collect($sections)
            ->map(fn ($s) => $s instanceof ScoutSection ? $s->value : $s)
            ->unique()
            ->map(fn ($s) => ['activity_id' => $this->id, 'section' => $s])
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('activity_sections')->insert($rows);
        }
    }

    /**
     * @return BelongsTo<CertificateTemplate, $this>
     */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<RoverAttendanceRecord, $this>
     */
    public function roverAttendanceRecords(): HasMany
    {
        return $this->hasMany(RoverAttendanceRecord::class);
    }

    /**
     * @return HasMany<ClassFee, $this>
     */
    public function classFees(): HasMany
    {
        return $this->hasMany(ClassFee::class);
    }

    public function targetSummary(): string
    {
        if ($this->all_students) {
            return 'All scouts';
        }

        $parts = array_map(fn (ScoutSection $s) => $s->value, $this->sections());

        foreach ($this->groups as $group) {
            $parts[] = $group->name;
        }

        return $parts === [] ? 'No roster' : implode(', ', $parts);
    }
}
