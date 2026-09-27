<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\ScoutSection;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'index_number' => fake()->unique()->numerify('IX#####'),
            'name' => fake()->name(),
            'national_id' => 'A'.fake()->unique()->numerify('#######'),
            'email' => fake()->unique()->safeEmail(),
            'gender' => fake()->randomElement(Gender::cases()),
            'permanent_address' => fake()->streetAddress(),
            'present_address' => fake()->streetAddress(),
            'date_of_birth' => fake()->dateTimeBetween('-17 years', '-6 years')->format('Y-m-d'),
            'parent_name' => fake()->name(),
            'primary_mobile' => fake()->numerify('7######'),
            'section' => ScoutSection::Scout,
            'status' => StudentStatus::Active,
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => StudentStatus::Pending, 'verified_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(['status' => StudentStatus::Inactive]);
    }

    public function section(ScoutSection $section): static
    {
        return $this->state(['section' => $section]);
    }

    public function rover(): static
    {
        return $this->section(ScoutSection::Rover);
    }
}
