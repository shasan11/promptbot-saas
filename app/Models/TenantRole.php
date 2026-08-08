<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class TenantRole extends SpatieRole
{
    protected $table = 'roles';

    protected $guard_name = 'tenant';

    protected $fillable = ['name', 'label', 'guard_name', 'is_protected'];

    protected function casts(): array
    {
        return [
            'is_protected' => 'boolean',
        ];
    }
}
