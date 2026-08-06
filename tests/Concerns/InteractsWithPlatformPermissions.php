<?php

namespace Tests\Concerns;

use App\Models\CentralUser;
use Database\Seeders\PlatformAuthorizationSeeder;

/**
 * Builds central administrators with real, database-backed Spatie
 * permissions/roles for authorization tests, instead of a bare factory user
 * (which now holds zero permissions and would 403 on every Superadmin route).
 */
trait InteractsWithPlatformPermissions
{
    protected function centralAdminWithPermissions(array $permissions = []): CentralUser
    {
        $this->seed(PlatformAuthorizationSeeder::class);

        $user = CentralUser::factory()->create(['role' => 'admin']);

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        return $user->fresh();
    }

    protected function platformOwner(): CentralUser
    {
        $this->seed(PlatformAuthorizationSeeder::class);

        $user = CentralUser::factory()->create(['role' => 'admin']);
        $user->assignRole('Platform Owner');

        return $user->fresh();
    }

    protected function readOnlyAuditor(): CentralUser
    {
        $this->seed(PlatformAuthorizationSeeder::class);

        $user = CentralUser::factory()->create(['role' => 'admin']);
        $user->assignRole('Read-Only Auditor');

        return $user->fresh();
    }
}
