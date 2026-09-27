<?php

namespace Database\Factories;

use App\Enums\RecordStatus;
use App\Models\Group;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Patrol',
            'type' => 'Patrol',
            'status' => RecordStatus::Active,
        ];
    }

    public function ledBy(User $leader): static
    {
        return $this->afterCreating(fn (Group $group) => $group->leaders()->attach($leader->id));
    }

    public function withMembers(Student ...$students): static
    {
        return $this->afterCreating(fn (Group $group) => $group->members()->attach(array_map(fn (Student $s) => $s->id, $students)));
    }
}
