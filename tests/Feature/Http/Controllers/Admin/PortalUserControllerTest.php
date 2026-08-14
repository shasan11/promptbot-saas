<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\CustomerAccount;
use App\Models\PortalUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class PortalUserControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_superadmin_can_create_and_attach_a_portal_user(): void
    {
        Notification::fake();
        $account = CustomerAccount::factory()->create();

        $this->actingAs($this->centralAdminWithPermissions(['customers.manage']), 'central')
            ->post(route('superadmin.customers.users.store'), [
                'name' => 'Portal Manager',
                'email' => 'portal.manager@example.test',
                'status' => 'active',
                'timezone' => 'Asia/Kathmandu',
                'account_id' => $account->id,
                'role' => 'admin',
            ])->assertRedirect();

        $user = PortalUser::where('email', 'portal.manager@example.test')->firstOrFail();
        $this->assertTrue($user->belongsToAccount($account));
        $this->assertSame('admin', $user->accounts()->firstOrFail()->pivot->role);
        $this->assertDatabaseHas('audit_logs', ['action' => 'portal_user.created']);
    }
}
