<?php

namespace Database\Factories;

use App\Models\CustomerAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CustomerAccountFactory extends Factory
{
    protected $model = CustomerAccount::class;

    public function definition(): array
    {
        return [
            'public_uuid' => (string) Str::uuid(), 'name' => fake()->company(),
            'account_number' => 'ACC-'.Str::upper(Str::random(10)), 'status' => 'active',
            'type' => 'business', 'billing_email' => fake()->companyEmail(),
            'default_currency' => 'USD', 'timezone' => 'UTC', 'locale' => 'en',
            'billing_mode' => 'per_service',
        ];
    }
}
