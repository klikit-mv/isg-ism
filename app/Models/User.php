<?php

namespace App\Models;

use App\Enums\ParentLinkStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'national_id', 'email', 'password', 'status', 'student_id',
        'verified_at', 'verified_by', 'avatar_path',
        'email_notifications_enabled', 'telegram_notifications_enabled', 'telegram_chat_id',
        'telegram_connect_token', 'telegram_connect_token_expires_at',
        'legacy_pin_hash', 'legacy_pin_salt', 'last_login_at', 'legacy_id',
    ];

    protected $hidden = [
        'password', 'remember_token', 'legacy_pin_hash', 'legacy_pin_salt', 'telegram_connect_token',
    ];

    /** @var array<string, bool>|null */
    private ?array $roleCache = null;

    /** @var array<string, bool>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'telegram_connect_token_expires_at' => 'datetime',
            'email_notifications_enabled' => 'boolean',
            'telegram_notifications_enabled' => 'boolean',
        ];
    }

    /**
     * @return HasMany<UserRole, $this>
     */
    public function roleRows(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * @return HasMany<UserPermission, $this>
     */
    public function permissionRows(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<ParentStudentLink, $this>
     */
    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'parent_user_id');
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function ledGroups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_leaders')->withTimestamps();
    }

    /**
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return $this->roleRows->map(fn (UserRole $row) => Role::from($row->role))->values();
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        return $this->permissionRows
            ->map(fn (UserPermission $row) => Permission::tryFrom($row->permission))
            ->filter()
            ->values();
    }

    public function hasRole(Role|string $role): bool
    {
        $value = $role instanceof Role ? $role->value : $role;
        $this->roleCache ??= $this->roleRows->pluck('role')->flip()->map(fn () => true)->all();

        return isset($this->roleCache[$value]);
    }

    /**
     * @param  array<int, Role|string>  $roles
     */
    public function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isAdmin(): bool
    {
        return $this->isActive() && $this->hasRole(Role::Admin);
    }

    public function isLeader(): bool
    {
        return $this->hasRole(Role::Leader);
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isLeader();
    }

    /**
     * Explicit grant, or implicit for admins.
     */
    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        $value = $permission instanceof Permission ? $permission->value : $permission;
        $this->permissionCache ??= $this->permissionRows->pluck('permission')->flip()->map(fn () => true)->all();

        return isset($this->permissionCache[$value]);
    }

    /**
     * @param  array<int, Role|string>  $roles
     */
    public function syncRoles(array $roles): void
    {
        $values = collect($roles)->map(fn ($role) => $role instanceof Role ? $role->value : $role)->unique()->values();
        $this->roleRows()->whereNotIn('role', $values)->delete();

        foreach ($values as $value) {
            $this->roleRows()->firstOrCreate(['role' => $value]);
        }

        $this->unsetRelation('roleRows');
        $this->roleCache = null;
    }

    public function assignRole(Role $role): void
    {
        $this->roleRows()->firstOrCreate(['role' => $role->value]);
        $this->unsetRelation('roleRows');
        $this->roleCache = null;
    }

    /**
     * @param  array<int, Permission|string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $values = collect($permissions)->map(fn ($p) => $p instanceof Permission ? $p->value : $p)->unique()->values();
        $this->permissionRows()->whereNotIn('permission', $values)->delete();

        foreach ($values as $value) {
            $this->permissionRows()->firstOrCreate(['permission' => $value]);
        }

        $this->unsetRelation('permissionRows');
        $this->permissionCache = null;
    }

    /**
     * Students this parent may see (approved links only).
     *
     * @return list<int>
     */
    public function approvedChildIds(): array
    {
        return $this->parentLinks()
            ->where('status', ParentLinkStatus::Approved->value)
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', UserStatus::Active->value);
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithRole(Builder $query, Role $role): void
    {
        $query->whereHas('roleRows', fn (Builder $q) => $q->where('role', $role->value));
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeWithPermission(Builder $query, Permission $permission): void
    {
        $query->where(function (Builder $q) use ($permission): void {
            $q->whereHas('permissionRows', fn (Builder $p) => $p->where('permission', $permission->value))
                ->orWhereHas('roleRows', fn (Builder $r) => $r->where('role', Role::Admin->value));
        });
    }

    /**
     * Profile picture URL: the uploaded avatar, else the linked scout's photo.
     */
    public function avatarUrl(): ?string
    {
        return photo_url($this->avatar_path) ?? photo_url($this->student?->photo_path);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }

    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegram_chat_id;
    }
}
