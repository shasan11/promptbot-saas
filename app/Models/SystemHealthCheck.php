<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class SystemHealthCheck extends Model
{
    use HasUuid;

    protected $fillable = ['name', 'status', 'message', 'context', 'checked_at'];

    protected $casts = [
        'context' => 'array',
        'checked_at' => 'datetime',
    ];
}
