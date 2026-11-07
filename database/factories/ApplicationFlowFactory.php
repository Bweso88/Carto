<?php

namespace Database\Factories;

use App\Enums\FlowProtocol;
use App\Models\Application;
use App\Models\ApplicationFlow;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ApplicationFlow> */
class ApplicationFlowFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source_application_id' => Application::factory(),
            'target_application_id' => Application::factory(),
            'protocol' => FlowProtocol::Rest,
            'is_encrypted' => true,
            'is_critical' => true,
        ];
    }

    public function between(Application $source, Application $target): static
    {
        return $this->state(['source_application_id' => $source->id, 'target_application_id' => $target->id]);
    }

    public function nonCritical(): static
    {
        return $this->state(['is_critical' => false]);
    }
}
