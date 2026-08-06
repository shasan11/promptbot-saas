<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.subscriptions.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_subscriptions(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['subscriptions.view']), 'central')
            ->get(route('superadmin.subscriptions.index'))
            ->assertOk();
    }

    public function test_changing_a_subscriptions_plan_syncs_the_tenants_plan(): void
    {
        $originalPlan = Plan::factory()->create(['trial_days' => 0]);
        $newPlan = Plan::factory()->create();
        $tenant = Tenant::create([
            'id' => 'subscription-update-test',
            'company_name' => 'Subscription Update Test',
            'slug' => 'subscription-update-test',
            'status' => 'active',
            'plan_id' => $originalPlan->id,
        ]);
        $subscription = app(SubscriptionService::class)->createInitialSubscription($tenant, $originalPlan);

        $this->actingAs($this->centralAdminWithPermissions(['subscriptions.update']), 'central')
            ->patch(route('superadmin.subscriptions.update', $subscription), [
                'plan_id' => $newPlan->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame($newPlan->id, $tenant->fresh()->plan_id);
        $this->assertSame($newPlan->id, $subscription->fresh()->plan_id);
    }

    public function test_view_permission_does_not_grant_update_access(): void
    {
        $tenant = Tenant::create([
            'id' => 'subscription-view-only-test',
            'company_name' => 'Subscription View Only',
            'slug' => 'subscription-view-only-test',
            'status' => 'active',
        ]);
        $plan = Plan::factory()->create();
        $subscription = app(SubscriptionService::class)->createInitialSubscription($tenant, $plan);

        $this->actingAs($this->centralAdminWithPermissions(['subscriptions.view']), 'central')
            ->patch(route('superadmin.subscriptions.update', $subscription), ['status' => 'cancelled'])
            ->assertForbidden();
    }
}
