<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plan = Plan::updateOrCreate(
            ['slug' => 'starter'],
            [
                'name' => 'Starter',
                'description' => 'Default starter tenant plan.',
                'monthly_price' => 0,
                'annual_price' => 0,
                'currency' => 'USD',
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 1,
                'is_recommended' => true,
                'user_limit' => 5,
                'storage_limit_mb' => 1024,
            ]
        );

        $features = Feature::query()->pluck('id', 'code');

        $plan->features()->syncWithoutDetaching([
            $features['users'] => ['enabled' => true, 'limit' => 5, 'unlimited' => false],
            $features['storage_mb'] => ['enabled' => true, 'limit' => 1024, 'unlimited' => false],
            $features['exports'] => ['enabled' => true, 'limit' => null, 'unlimited' => true],
        ]);
    }
}
