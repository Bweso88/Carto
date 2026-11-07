<?php

namespace Database\Factories;

use App\Enums\ServerKind;
use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Server> */
class ServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hostname' => 'srv-'.fake()->unique()->lexify('????').'-'.fake()->numerify('##'),
            'kind' => ServerKind::Virtual,
            'status' => ServerStatus::Active,
        ];
    }

    public function physical(): static
    {
        return $this->state(['kind' => ServerKind::Physical]);
    }

    public function hostedOn(Server $host): static
    {
        return $this->state(['kind' => ServerKind::Virtual, 'host_id' => $host->id]);
    }
}
