<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use Illuminate\Database\Seeder;
use RuntimeException;

class CentralUserSeeder extends Seeder
{
    /**
     * Passwords that must never be accepted for the seeded platform owner.
     */
    private const INSECURE_PASSWORDS = ['password', 'Password1', 'ChangeMeDemo123!', 'secret', '12345678'];

    public function run(): void
    {
        $email = env('CENTRAL_ADMIN_EMAIL', 'admin@example.com');
        $password = (string) env('CENTRAL_ADMIN_PASSWORD', '');

        if (app()->environment('production') && (
            $password === ''
            || strlen($password) < 12
            || in_array($password, self::INSECURE_PASSWORDS, true)
        )) {
            throw new RuntimeException(
                'Refusing to seed the central administrator with an insecure or default CENTRAL_ADMIN_PASSWORD in production. '.
                'Set a strong, unique CENTRAL_ADMIN_PASSWORD (12+ characters) before seeding.'
            );
        }

        CentralUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('CENTRAL_ADMIN_NAME', 'Super Administrator'),
                'password' => $password !== '' ? $password : 'password',
                'role' => 'super_admin',
                'is_active' => true,
            ]
        );
    }
}
