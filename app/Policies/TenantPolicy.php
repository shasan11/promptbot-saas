<?php

namespace App\Policies;

use App\Models\PortalUser;
use App\Models\Tenant;
use App\Policies\Concerns\ChecksPortalMembership;

class TenantPolicy
{
    use ChecksPortalMembership;

    public function view(PortalUser $user, Tenant $tenant): bool
    {
        return $tenant->customerAccount && $this->hasWorkspaceAccess($user, $tenant);
    }

    public function update(PortalUser $user, Tenant $tenant): bool
    {
        return $tenant->customerAccount && $this->hasWorkspaceAccess($user, $tenant)
            && $this->hasCapability($user, $tenant->customerAccount, 'can_manage_services');
    }
}
