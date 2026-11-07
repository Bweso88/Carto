<?php

namespace Database\Factories;

use App\Models\IpAddress;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IpAddress> */
class IpAddressFactory extends Factory
{
    public function definition(): array
    {
        return [
            'server_id' => Server::factory(),
            'address' => fake()->unique()->localIpv4(),
            'is_primary' => true,
        ];
    }
}
