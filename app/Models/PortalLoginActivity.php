<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class PortalLoginActivity extends Model
{
    use CentralConnection, HasUuid;
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['successful' => 'boolean', 'metadata' => 'array', 'created_at' => 'datetime'];
}
