<?php

namespace App\Policies;

use App\Models\CentralUser;
use App\Models\PortalUser;

class PortalUserPolicy
{
    public function viewAny(CentralUser $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(CentralUser $user, PortalUser $portalUser): bool
    {
        return $user->can('customers.view');
    }

    public function update(CentralUser $user, PortalUser $portalUser): bool
    {
        return $user->can('customers.manage');
    }
}
