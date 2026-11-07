<?php

namespace Database\Factories;

use App\Enums\TechnologyCategory;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Technology> */
class TechnologyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(TechnologyCategory::cases()),
            'name' => ucfirst(fake()->unique()->word()),
            'version' => (string) fake()->numberBetween(1, 20),
        ];
    }

    public function os(): static
    {
        return $this->state(['category' => TechnologyCategory::OperatingSystem]);
    }

    public function obsolete(): static
    {
        return $this->state(['end_of_life_at' => now()->subYear()]);
    }
}
