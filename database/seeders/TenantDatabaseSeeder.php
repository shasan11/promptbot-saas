<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TenantAuthorizationSeeder::class);
        $this->call(ConnectionIntegrationSeeder::class);

        Setting::firstOrCreate(['key' => 'general.workspace_name'], ['value' => ['value' => tenant('company_name')]]);
        Setting::firstOrCreate(['key' => 'branding.sender_name'], ['value' => ['value' => tenant('company_name')]]);
    }
}
