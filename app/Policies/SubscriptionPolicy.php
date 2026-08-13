<?php

namespace App\Policies;

use App\Models\PortalUser;
use App\Models\Subscription;
use App\Policies\Concerns\ChecksPortalMembership;

class SubscriptionPolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, Subscription $subscription): bool
    {
        return $subscription->customerAccount && $this->hasCapability($user, $subscription->customerAccount, 'can_manage_billing')
            && (! $subscription->tenant || $this->hasWorkspaceAccess($user, $subscription->tenant));
    }

    public function update(PortalUser $user, Subscription $subscription): bool { return $this->view($user, $subscription); }
}
