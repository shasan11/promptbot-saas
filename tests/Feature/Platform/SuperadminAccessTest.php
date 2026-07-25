<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_owner_role_has_full_superadmin_access(): void
    {
        $this->seed(PlatformAuthorizationSeeder::class);

        $admin = CentralUser::factory()->create(['role' => 'platform_owner', 'two_factor_required' => false]);
        $this->seed(PlatformAuthorizationSeeder::class);
        $admin->refresh();

        $this->assertSame('platform_owner', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasRole('Platform Owner'));

        foreach ([
            route('superadmin.dashboard'),
            route('superadmin.billing.resource.index', 'payments'),
            route('superadmin.platform.resource.index', 'usage'),
            route('superadmin.website.resource.index'),
            route('superadmin.operations.health'),
            route('superadmin.audit-logs.index'),
            route('superadmin.settings.edit'),
        ] as $url) {
            $this->actingAs($admin, 'central')->get($url)->assertOk();
        }
    }
}
