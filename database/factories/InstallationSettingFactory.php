<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstallationSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'key' => fake()->word(),
            'value' => fake()->text(),
            'encrypted' => fake()->boolean(),
        ];
    }
}
