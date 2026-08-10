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
            ['name' => 'AI Platform', 'code' => 'ai_platform', 'type' => 'boolean', 'description' => 'Tenant AI agents and copilot'],
            ['name' => 'AI Monthly Tokens', 'code' => 'ai_monthly_tokens', 'type' => 'limited', 'description' => 'Monthly AI token allowance'],
            ['name' => 'AI Agents', 'code' => 'ai_agents', 'type' => 'limited', 'description' => 'Deployed AI agents'],
            ['name' => 'AI Autonomous Replies', 'code' => 'ai_autonomous_replies', 'type' => 'boolean', 'description' => 'Safety-gated autonomous customer replies'],
        ])->each(fn (array $feature) => Feature::updateOrCreate(['code' => $feature['code']], $feature));
    }
}
