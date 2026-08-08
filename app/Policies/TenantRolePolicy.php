<?php

namespace App\Policies;

use App\Models\TenantRole;
use App\Models\User;

class TenantRolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('roles.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('roles.create');
    }

    public function update(User $actor, TenantRole $role): bool
    {
        if (! $actor->can('roles.update')) {
            return false;
        }

        // Protected system roles (Tenant Owner / Tenant Administrator) keep
        // their full permission set — editable in identity only, never stripped.
        return true;
    }

    public function delete(User $actor, TenantRole $role): bool
    {
        if (! $actor->can('roles.delete')) {
            return false;
        }

        return ! $role->is_protected;
    }

    public function assign(User $actor): bool
    {
        return $actor->can('roles.assign');
    }
}
