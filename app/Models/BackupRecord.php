<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class BackupRecord extends Model
{
    use HasUuid;

    protected $fillable = ['scope', 'tenant_id', 'status', 'disk', 'path', 'size', 'encrypted', 'verified_at'];

    protected $casts = [
        'encrypted' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
