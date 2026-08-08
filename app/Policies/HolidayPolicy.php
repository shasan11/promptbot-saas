<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('workspace.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('workspace.manage_business_hours');
    }

    public function update(User $actor, Holiday $holiday): bool
    {
        return $actor->can('workspace.manage_business_hours');
    }

    public function delete(User $actor, Holiday $holiday): bool
    {
        return $actor->can('workspace.manage_business_hours');
    }
}
