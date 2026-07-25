<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CentralUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! env('CENTRAL_ADMIN_EMAIL') || ! env('CENTRAL_ADMIN_PASSWORD')) {
            return;
        }

        CentralUser::updateOrCreate(
            ['email' => env('CENTRAL_ADMIN_EMAIL')],
            [
                'name' => env('CENTRAL_ADMIN_NAME', 'Super Administrator'),
                'password' => Hash::make(env('CENTRAL_ADMIN_PASSWORD')),
                'role' => 'platform_owner',
                'is_active' => true,
                'two_factor_required' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
