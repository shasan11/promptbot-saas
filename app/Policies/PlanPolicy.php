<?php

namespace App\Policies;

use App\Enums\SubscriptionStatus;
use App\Models\CentralUser;
use App\Models\Plan;

class PlanPolicy
{
    /**
     * Route middleware already enforces the `plans.archive` permission; this
     * policy adds the domain rule that a plan backing a live subscription
     * cannot be archived out from under its tenants.
     */
    public function delete(CentralUser $user, Plan $plan): bool
    {
        return ! $plan->subscriptions()
            ->whereIn('status', [
                SubscriptionStatus::Active,
                SubscriptionStatus::Trial,
                SubscriptionStatus::PastDue,
            ])
            ->exists();
    }
}
