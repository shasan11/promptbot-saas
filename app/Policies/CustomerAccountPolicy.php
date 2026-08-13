<?php

namespace App\Policies;

use App\Models\CustomerAccount;
use App\Models\PortalUser;
use App\Policies\Concerns\ChecksPortalMembership;

class CustomerAccountPolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, CustomerAccount $account): bool { return (bool) $this->membership($user, $account); }
    public function manageServices(PortalUser $user, CustomerAccount $account): bool { return $this->hasCapability($user, $account, 'can_manage_services'); }
    public function manageBilling(PortalUser $user, CustomerAccount $account): bool
    {
        $membership = $this->membership($user, $account);
        return $membership && (in_array($membership->role, ['owner', 'admin', 'billing'], true) || (bool) $membership->can_manage_billing);
    }
    public function manageMembers(PortalUser $user, CustomerAccount $account): bool { return $this->hasCapability($user, $account, 'can_manage_members'); }
    public function manageSupport(PortalUser $user, CustomerAccount $account): bool { return $this->hasCapability($user, $account, 'can_manage_support'); }

    public function transferOwnership(PortalUser $user, CustomerAccount $account): bool
    {
        return $this->membership($user, $account)?->role === 'owner';
    }
}
