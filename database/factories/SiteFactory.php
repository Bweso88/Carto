<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => 'DC '.fake()->unique()->city(), 'kind' => 'datacenter'];
    }
}
