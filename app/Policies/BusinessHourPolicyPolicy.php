<?php

namespace App\Policies;

use App\Models\BusinessHourPolicy;
use App\Models\User;

class BusinessHourPolicyPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('workspace.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('workspace.manage_business_hours');
    }

    public function update(User $actor, BusinessHourPolicy $policy): bool
    {
        return $actor->can('workspace.manage_business_hours');
    }

    public function delete(User $actor, BusinessHourPolicy $policy): bool
    {
        return $actor->can('workspace.manage_business_hours') && ! $policy->is_default;
    }
}
