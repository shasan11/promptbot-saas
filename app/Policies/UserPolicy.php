<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->can('users.update');
    }

    public function manageRoles(User $actor, User $target): bool
    {
        if (! $actor->can('users.manage_roles')) {
            return false;
        }

        // Never allow a non-owner to grant or revoke roles from a Tenant Owner.
        if ($target->hasRole('Tenant Owner') && ! $actor->hasRole('Tenant Owner')) {
            return false;
        }

        return true;
    }

    public function suspend(User $actor, User $target): bool
    {
        if (! $actor->can('users.suspend')) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        return ! $this->isLastActiveOwner($target);
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->can('users.delete')) {
            return false;
        }

        if ($actor->is($target)) {
            return false;
        }

        return ! $this->isLastActiveOwner($target);
    }

    public function manageSessions(User $actor, User $target): bool
    {
        return $actor->can('users.manage_sessions');
    }

    private function isLastActiveOwner(User $target): bool
    {
        if (! $target->hasRole('Tenant Owner')) {
            return false;
        }

        return User::role('Tenant Owner')->where('status', 'active')->count() <= 1;
    }
}
