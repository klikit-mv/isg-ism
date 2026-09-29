<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\ParentLinkStatus;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'index_number', 'name', 'national_id', 'email', 'photo_path', 'gender',
        'permanent_address', 'present_address', 'date_of_birth', 'parent_name',
        'primary_mobile', 'secondary_mobile', 'section', 'class_name', 'patrol',
        'status', 'verified_at', 'verified_by', 'legacy_id',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'section' => ScoutSection::class,
            'status' => StudentStatus::class,
            'date_of_birth' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<ParentStudentLink, $this>
     */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class);
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')->withTimestamps();
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<ClassFee, $this>
     */
    public function classFees(): HasMany
    {
        return $this->hasMany(ClassFee::class);
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function approvedParent(): ?User
    {
        return $this->parentLinks()
            ->where('status', ParentLinkStatus::Approved->value)
            ->with('parent')
            ->first()?->parent;
    }

    public function isRover(): bool
    {
        return $this->section === ScoutSection::Rover;
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', StudentStatus::Active->value);
    }

    /**
     * @param  Builder<Student>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $q) use ($term): void {
            $like = '%'.$term.'%';
            $q->where('name', 'like', $like)
                ->orWhere('national_id', 'like', $like)
                ->orWhere('index_number', 'like', $like);
        });
    }
}
