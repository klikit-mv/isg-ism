<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $pin;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'national_id' => 'A'.fake()->unique()->numerify('######'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$pin ??= Hash::make('1234'),
            'status' => UserStatus::Active,
            'verified_at' => now(),
            'email_notifications_enabled' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['status' => UserStatus::Inactive]);
    }

    public function unverified(): static
    {
        return $this->state(['status' => UserStatus::Inactive, 'verified_at' => null]);
    }

    public function withRoles(Role ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            foreach ($roles as $role) {
                $user->assignRole($role);
            }
        });
    }

    public function withPermissions(Permission ...$permissions): static
    {
        return $this->afterCreating(fn (User $user) => $user->syncPermissions($permissions));
    }

    public function admin(): static
    {
        return $this->withRoles(Role::Admin);
    }

    public function leader(): static
    {
        return $this->withRoles(Role::Leader);
    }

    public function parentRole(): static
    {
        return $this->withRoles(Role::Parent);
    }

    /**
     * A student account linked to a new or given scout.
     */
    public function forStudent(?Student $student = null): static
    {
        return $this->state(function () use ($student) {
            $student ??= Student::factory()->create();

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'national_id' => $student->national_id,
                'email' => $student->email,
            ];
        })->withRoles(Role::Student);
    }
}
