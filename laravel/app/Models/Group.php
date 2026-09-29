<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\ScoutSection;
use App\Models\Concerns\HasUuid;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = ['name', 'type', 'section', 'owner_id', 'status', 'legacy_id'];

    protected function casts(): array
    {
        return ['status' => RecordStatus::class, 'section' => ScoutSection::class];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_members')->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function leaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_leaders')->withTimestamps();
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function assistantLeaders(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'group_assistant_leaders')->withTimestamps();
    }
}
