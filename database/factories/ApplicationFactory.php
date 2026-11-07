<?php

namespace Database\Factories;

use App\Enums\ApplicationType;
use App\Enums\Criticality;
use App\Enums\LifecycleStatus;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Application> */
class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => ApplicationType::Business,
            'lifecycle_status' => LifecycleStatus::Production,
            'criticality' => fake()->randomElement(Criticality::cases()),
        ];
    }

    public function critical(): static
    {
        return $this->state(['criticality' => Criticality::Critical]);
    }
}
