<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;

class CentralUserSeeder extends Seeder
{
    public function run(): void
    {
        CentralUser::updateOrCreate(
            ['email' => env('CENTRAL_ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('CENTRAL_ADMIN_NAME', 'Super Administrator'),
                'password' => env('CENTRAL_ADMIN_PASSWORD', 'password'),
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
