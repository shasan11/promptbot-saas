<?php

namespace App\Services\Platform;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Subscription is the source of truth for a tenant's plan/billing state.
 * Tenant.plan_id is kept as a synchronized denormalized value so existing
 * queries/relations against `tenant.plan` keep working, but it must only
 * ever be written here, never set independently by other code paths.
 */
class SubscriptionService
{
    /**
     * Create the tenant's first subscription right after provisioning
     * succeeds. Idempotent: a tenant that already has a subscription (e.g.
     * a retried provisioning run) is left untouched.
     */
    public function createInitialSubscription(Tenant $tenant, ?Plan $plan = null): ?Subscription
    {
        if ($tenant->subscriptions()->exists()) {
            return $tenant->subscriptions()->latest()->first();
        }

        $plan ??= $tenant->plan;

        if (! $plan) {
            return null;
        }

        $now = now();
        $onTrial = $plan->trial_days > 0;
        $periodEnd = $onTrial ? $now->copy()->addDays($plan->trial_days) : $now->copy()->addMonth();

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $onTrial ? SubscriptionStatus::Trial : SubscriptionStatus::Active,
            'billing_interval' => 'monthly',
            'starts_at' => $now,
            'trial_ends_at' => $onTrial ? $periodEnd : null,
            'current_period_starts_at' => $now,
            'current_period_ends_at' => $periodEnd,
        ]);

        $this->syncTenantPlan($subscription);

        return $subscription;
    }

    /**
     * Keep Tenant.plan_id aligned with the subscription's plan. Call this
     * any time a subscription's plan_id is written.
     */
    public function syncTenantPlan(Subscription $subscription): void
    {
        $tenant = $subscription->tenant;

        if ($tenant && $tenant->plan_id !== $subscription->plan_id) {
            $tenant->forceFill(['plan_id' => $subscription->plan_id])->save();
        }
    }
}
