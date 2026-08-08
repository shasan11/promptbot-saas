<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('teams.view');
    }

    public function view(User $actor, Team $team): bool
    {
        return $actor->can('teams.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('teams.create');
    }

    public function update(User $actor, Team $team): bool
    {
        return $actor->can('teams.update');
    }

    public function delete(User $actor, Team $team): bool
    {
        return $actor->can('teams.delete');
    }

    public function manageMembers(User $actor, Team $team): bool
    {
        return $actor->can('teams.manage_members');
    }
}
