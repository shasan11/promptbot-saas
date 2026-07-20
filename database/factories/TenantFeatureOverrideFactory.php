<?php

namespace Database\Factories;

use App\Models\Feature;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFeatureOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->word(),
            'feature_id' => Feature::factory(),
            'enabled' => fake()->boolean(),
            'limit' => fake()->numberBetween(-10000, 10000),
            'unlimited' => fake()->boolean(),
            'metadata' => '{}',
        ];
    }
}
