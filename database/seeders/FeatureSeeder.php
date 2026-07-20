<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            ['name' => 'Users', 'code' => 'users', 'type' => 'limited', 'description' => 'Tenant user seats'],
            ['name' => 'Storage', 'code' => 'storage_mb', 'type' => 'limited', 'description' => 'Tenant storage in MB'],
            ['name' => 'Exports', 'code' => 'exports', 'type' => 'boolean', 'description' => 'Data export tools'],
            ['name' => 'Custom Domain', 'code' => 'custom_domain', 'type' => 'boolean', 'description' => 'Verified custom domains'],
        ])->each(fn (array $feature) => Feature::updateOrCreate(['code' => $feature['code']], $feature));
    }
}
