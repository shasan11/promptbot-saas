<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TenantAuthorizationSeeder::class);

        Setting::firstOrCreate(['key' => 'branding'], ['value' => ['logo' => null, 'sender_name' => tenant('company_name')]]);
    }
}
