<?php

namespace Database\Factories;

use App\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Environment> */
class EnvironmentFactory extends Factory
{
    public function definition(): array
    {
        $code = strtoupper(fake()->unique()->lexify('ENV???'));

        return ['code' => $code, 'name' => ucfirst(strtolower($code)), 'is_production' => false];
    }

    public function production(): static
    {
        return $this->state(['code' => 'PROD', 'name' => 'Production', 'is_production' => true]);
    }
}
