<?php

namespace Tests;

use App\Models\CentralUser;
use App\Models\PlatformPermission;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function centralUserWithPermissions(array|string $permissions): CentralUser
    {
        $permissions = (array) $permissions;
        $user = CentralUser::factory()->create();

        foreach ($permissions as $permission) {
            PlatformPermission::findOrCreate($permission, 'central');
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
