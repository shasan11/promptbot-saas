<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalNotification extends Model
{
    use CentralConnection, HasUuid;
    protected $guarded = [];
    protected $casts = ['customer_account_id' => 'integer', 'portal_user_id' => 'integer', 'data' => 'array', 'read_at' => 'datetime'];
}
