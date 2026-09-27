<?php

namespace App\Services;

use App\Enums\ParentLinkStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Exceptions\ScoutException;
use App\Models\ParentStudentLink;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        private AuditLogService $audit,
        private ParentLinkService $parentLinks,
        private NotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, national_id: string, email?: string|null, pin: string, status?: string|null, roles?: list<string>, permissions?: list<string>, student_id?: int|null}  $data
     */
    public function create(array $data, User $actor): User
    {
        return DB::transaction(function () use ($data, $actor): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'national_id' => Str::upper(trim($data['national_id'])),
                'email' => $data['email'] ?? null,
                'password' => $data['pin'],
                'status' => $data['status'] ?? UserStatus::Active->value,
                'student_id' => $data['student_id'] ?? null,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ]);

            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);

            $this->audit->record('user.created', $user, [
                'national_id' => $user->national_id,
                'roles' => $data['roles'] ?? [],
                'permissions' => $data['permissions'] ?? [],
            ], $actor);

            return $user;
        });
    }

    /**
     * Update name, email, status, roles, permissions and linked student. The PIN is never part of an edit.
     *
     * @param  array{name: string, email?: string|null, status: string, roles?: list<string>, permissions?: list<string>, student_id?: int|null}  $data
     */
    public function update(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'status' => $data['status'],
                'student_id' => $data['student_id'] ?? null,
            ])->save();

            $user->syncRoles($data['roles'] ?? []);
            $user->syncPermissions($data['permissions'] ?? []);

            $this->audit->record('user.updated', $user, [
                'status' => $data['status'],
                'roles' => $data['roles'] ?? [],
                'permissions' => $data['permissions'] ?? [],
            ], $actor);

            return $user;
        });
    }

    /**
     * Reset a PIN: clears legacy hashes and ends every session for that user.
     */
    public function resetPin(User $user, string $pin, User $actor): void
    {
        DB::transaction(function () use ($user, $pin, $actor): void {
            $user->forceFill([
                'password' => $pin,
                'legacy_pin_hash' => null,
                'legacy_pin_salt' => null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
            $this->audit->record('user.pin_reset', $user, [], $actor);
        });
    }

    public function setStatus(User $user, UserStatus $status, User $actor): void
    {
        $user->forceFill(['status' => $status])->save();
        $this->audit->record('user.status_changed', $user, ['status' => $status], $actor);
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new ScoutException('You cannot delete your own account.');
        }

        $this->audit->record('user.deleted', $user, ['national_id' => $user->national_id], $actor);
        $user->delete();
    }

    /**
     * Public parent registration: an inactive parent and one pending link per child.
     *
     * @param  array{name: string, national_id: string, email: string, pin: string}  $data
     * @param  list<string>  $childNationalIds
     */
    public function registerParent(array $data, array $childNationalIds): User
    {
        return DB::transaction(function () use ($data, $childNationalIds): User {
            $children = [];

            foreach (array_unique(array_map(fn ($id) => Str::upper(trim($id)), $childNationalIds)) as $nationalId) {
                $student = Student::query()->where('national_id', $nationalId)->first();

                if ($student === null) {
                    throw new ScoutException("No scout matches National ID {$nationalId}.");
                }

                $this->parentLinks->assertStudentHasNoOtherParent($student);
                $children[] = $student;
            }

            if ($children === []) {
                throw new ScoutException('Add at least one child by National ID.');
            }

            $parent = User::query()->create([
                'name' => $data['name'],
                'national_id' => Str::upper(trim($data['national_id'])),
                'email' => $data['email'],
                'password' => $data['pin'],
                'status' => UserStatus::Inactive,
            ]);
            $parent->assignRole(Role::Parent);

            foreach ($children as $student) {
                ParentStudentLink::query()->create([
                    'parent_user_id' => $parent->id,
                    'student_id' => $student->id,
                    'status' => ParentLinkStatus::Pending,
                ]);
            }

            $this->audit->record('parent.registered', $parent, [
                'children' => array_map(fn (Student $s) => $s->national_id, $children),
            ], $parent);

            DB::afterCommit(fn () => $this->notifications->parentRegistered($parent));

            return $parent;
        });
    }

    public function verifyParentRegistration(User $parent, User $actor): void
    {
        DB::transaction(function () use ($parent, $actor): void {
            $parent->forceFill([
                'status' => UserStatus::Active,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ])->save();

            foreach ($parent->parentLinks()->where('status', ParentLinkStatus::Pending->value)->get() as $link) {
                $this->parentLinks->setStatus($link, ParentLinkStatus::Approved, $actor);
            }

            $this->audit->record('parent.verified', $parent, [], $actor);
        });

        $this->notifications->parentVerified($parent);
    }

    public function rejectParentRegistration(User $parent, User $actor): void
    {
        DB::transaction(function () use ($parent, $actor): void {
            $parent->forceFill([
                'status' => UserStatus::Inactive,
                'verified_at' => now(),
                'verified_by' => $actor->id,
            ])->save();

            $parent->parentLinks()->where('status', ParentLinkStatus::Pending->value)->update(['status' => ParentLinkStatus::Rejected->value]);
            $this->audit->record('parent.declined', $parent, [], $actor);
        });
    }

    /**
     * @return list<string>
     */
    public static function roleValues(): array
    {
        return Role::values();
    }

    /**
     * @return list<string>
     */
    public static function permissionValues(): array
    {
        return Permission::values();
    }
}
