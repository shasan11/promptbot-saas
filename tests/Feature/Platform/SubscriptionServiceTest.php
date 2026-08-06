<?php

namespace Tests\Feature\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(Plan $plan): Tenant
    {
        return Tenant::create([
            'id' => 'sub-service-test-'.$plan->id,
            'company_name' => 'Subscription Service Test',
            'slug' => 'sub-service-test-'.$plan->id,
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);
    }

    public function test_a_paid_plan_with_no_trial_creates_an_active_subscription(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 0]);
        $tenant = $this->tenant($plan);

        $subscription = app(SubscriptionService::class)->createInitialSubscription($tenant);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
        $this->assertNotNull($subscription->current_period_ends_at);
        $this->assertSame($plan->id, $tenant->fresh()->plan_id);
    }

    public function test_a_plan_with_trial_days_creates_a_trial_subscription(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 14]);
        $tenant = $this->tenant($plan);

        $subscription = app(SubscriptionService::class)->createInitialSubscription($tenant);

        $this->assertSame(SubscriptionStatus::Trial, $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isAfter(now()->addDays(13)));
    }

    public function test_creating_an_initial_subscription_twice_is_idempotent(): void
    {
        $plan = Plan::factory()->create(['trial_days' => 0]);
        $tenant = $this->tenant($plan);
        $service = app(SubscriptionService::class);

        $first = $service->createInitialSubscription($tenant);
        $second = $service->createInitialSubscription($tenant);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $tenant->subscriptions()->count());
    }

    public function test_syncing_tenant_plan_updates_the_tenant_when_subscription_plan_changes(): void
    {
        $originalPlan = Plan::factory()->create(['trial_days' => 0]);
        $newPlan = Plan::factory()->create();
        $tenant = $this->tenant($originalPlan);
        $service = app(SubscriptionService::class);

        $subscription = $service->createInitialSubscription($tenant);
        $subscription->update(['plan_id' => $newPlan->id]);
        $service->syncTenantPlan($subscription);

        $this->assertSame($newPlan->id, $tenant->fresh()->plan_id);
    }
}
