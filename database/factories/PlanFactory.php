<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'description' => fake()->text(),
            'monthly_price' => fake()->randomFloat(2, 0, 99999999.99),
            'annual_price' => fake()->randomFloat(2, 0, 99999999.99),
            'currency' => fake()->regexify('[A-Za-z0-9]{3}'),
            'trial_days' => fake()->numberBetween(-10000, 10000),
            'is_active' => fake()->boolean(),
            'sort_order' => fake()->numberBetween(-10000, 10000),
            'is_recommended' => fake()->boolean(),
            'user_limit' => fake()->numberBetween(-10000, 10000),
            'storage_limit_mb' => fake()->numberBetween(-10000, 10000),
            'resource_limits' => '{}',
        ];
    }
}
