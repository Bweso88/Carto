<?php

namespace Database\Factories;

use App\Models\BusinessDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessDomain> */
class BusinessDomainFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->jobTitle()];
    }
}
