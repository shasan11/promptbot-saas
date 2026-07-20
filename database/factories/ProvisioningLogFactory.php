<?php

namespace Database\Factories;

use App\Models\CentralUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProvisioningLogFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->word(),
            'step' => fake()->word(),
            'status' => fake()->word(),
            'message' => fake()->text(),
            'context' => '{}',
            'created_by' => CentralUser::factory(),
        ];
    }
}
