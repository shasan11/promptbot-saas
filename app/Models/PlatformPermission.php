<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Spatie\Permission\Models\Permission as SpatiePermission;

class PlatformPermission extends SpatiePermission
{
    use HasUuid;

    protected $table = 'platform_permissions';

    protected $guard_name = 'central';
}
