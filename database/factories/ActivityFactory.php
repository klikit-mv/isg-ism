<?php

namespace Database\Factories;

use App\Enums\ScoutSection;
use App\Models\Activity;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->words(3, true)),
            'date' => now()->toDateString(),
            'details' => fake()->sentence(),
            'all_students' => false,
            'charge_fee' => false,
            'fee_amount' => null,
        ];
    }

    public function forAll(): static
    {
        return $this->state(['all_students' => true]);
    }

    public function charged(string $amount = '50.00'): static
    {
        return $this->state(['charge_fee' => true, 'fee_amount' => $amount]);
    }

    public function forGroups(Group ...$groups): static
    {
        return $this->afterCreating(fn (Activity $activity) => $activity->groups()->attach(array_map(fn (Group $g) => $g->id, $groups)));
    }

    /**
     * @param  list<ScoutSection>  $sections
     */
    public function forSections(array $sections): static
    {
        return $this->afterCreating(fn (Activity $activity) => $activity->syncSections($sections));
    }
}
