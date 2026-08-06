<?php

namespace Tests\Feature\Platform;

use App\Models\CentralUser;
use Database\Seeders\CentralUserSeeder;
use Database\Seeders\PlatformAuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_admin_email_has_full_superadmin_access(): void
    {
        $this->seed(CentralUserSeeder::class);
        $this->seed(PlatformAuthorizationSeeder::class);

        $admin = CentralUser::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('super_admin', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasRole('Platform Owner'));

        foreach ([
            route('superadmin.dashboard'),
            route('superadmin.billing.payments.index'),
            route('superadmin.billing.invoices.index'),
            route('superadmin.tickets.index'),
            route('superadmin.reports.index'),
            route('superadmin.website.index'),
            route('superadmin.operations.health'),
            route('superadmin.system.settings.index'),
        ] as $url) {
            $this->actingAs($admin, 'central')->get($url)->assertOk();
        }
    }
}
