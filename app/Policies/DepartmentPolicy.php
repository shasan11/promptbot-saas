<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('departments.view');
    }

    public function view(User $actor, Department $department): bool
    {
        return $actor->can('departments.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('departments.create');
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->can('departments.update');
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->can('departments.delete');
    }
}
