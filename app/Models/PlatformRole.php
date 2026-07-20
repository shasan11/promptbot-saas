<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Spatie\Permission\Models\Role as SpatieRole;

class PlatformRole extends SpatieRole
{
    use HasUuid;

    protected $table = 'platform_roles';

    protected $guard_name = 'central';
}
