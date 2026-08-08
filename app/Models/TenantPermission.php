<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class TenantPermission extends SpatiePermission
{
    protected $table = 'permissions';

    protected $guard_name = 'tenant';

    protected $fillable = ['name', 'label', 'guard_name', 'group'];
}
