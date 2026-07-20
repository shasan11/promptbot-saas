<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->word(),
            'plan_id' => Plan::factory(),
            'status' => fake()->word(),
            'billing_interval' => fake()->word(),
            'starts_at' => fake()->dateTime(),
            'trial_ends_at' => fake()->dateTime(),
            'current_period_starts_at' => fake()->dateTime(),
            'current_period_ends_at' => fake()->dateTime(),
            'cancelled_at' => fake()->dateTime(),
            'grace_ends_at' => fake()->dateTime(),
            'external_provider' => fake()->word(),
            'external_id' => fake()->word(),
            'metadata' => '{}',
        ];
    }
}
