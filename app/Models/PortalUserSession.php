<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalUserSession extends Model
{
    use CentralConnection, HasUuid;
    protected $guarded = [];
    protected $hidden = ['session_hash'];
    protected $casts = ['last_activity_at' => 'datetime', 'revoked_at' => 'datetime'];
}
