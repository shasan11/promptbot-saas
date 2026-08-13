<?php

namespace Database\Factories;

use App\Enums\PortalUserStatus;
use App\Models\PortalUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PortalUserFactory extends Factory
{
    protected $model = PortalUser::class;

    public function definition(): array
    {
        return [
            'public_uuid' => (string) Str::uuid(), 'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(), 'email_verified_at' => now(),
            'password' => Hash::make('password'), 'status' => PortalUserStatus::Active,
            'timezone' => 'UTC', 'locale' => 'en',
        ];
    }
}
