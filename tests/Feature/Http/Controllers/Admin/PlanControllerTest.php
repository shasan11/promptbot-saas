<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('superadmin.plans.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.plans.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_plans(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['plans.view']), 'central')
            ->get(route('superadmin.plans.index'))
            ->assertOk();
    }

    public function test_view_permission_does_not_grant_create_access(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['plans.view']), 'central')
            ->get(route('superadmin.plans.create'))
            ->assertForbidden();
    }

    public function test_admin_with_create_permission_can_create_a_plan(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['plans.create']), 'central')
            ->post(route('superadmin.plans.store'), [
                'name' => 'Growth',
                'slug' => 'growth',
                'monthly_price' => 49,
                'annual_price' => 490,
                'currency' => 'USD',
                'trial_days' => 14,
                'is_active' => true,
                'sort_order' => 1,
                'is_recommended' => false,
            ])
            ->assertRedirect(route('superadmin.plans.index'));

        $this->assertDatabaseHas('plans', ['slug' => 'growth']);
    }

    public function test_archiving_a_plan_with_an_active_subscription_is_blocked(): void
    {
        $plan = Plan::factory()->create();
        $tenant = Tenant::create([
            'id' => 'plan-archive-test-tenant',
            'company_name' => 'Plan Archive Test',
            'slug' => 'plan-archive-test-tenant',
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);
        Subscription::factory()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $this->actingAs($this->centralAdminWithPermissions(['plans.archive']), 'central')
            ->delete(route('superadmin.plans.destroy', $plan))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($plan);
    }

    public function test_archiving_a_plan_without_active_subscriptions_succeeds(): void
    {
        $plan = Plan::factory()->create();

        $this->actingAs($this->centralAdminWithPermissions(['plans.archive']), 'central')
            ->delete(route('superadmin.plans.destroy', $plan))
            ->assertRedirect();

        $this->assertSoftDeleted($plan);
    }
}
