<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    use HasUuid;
    use UsesCentralConnection;

    protected $fillable = [
        'group',
        'key',
        'value',
        'encrypted',
        'is_sensitive',
    ];

    protected $casts = [
        'value' => 'encrypted:array',
        'encrypted' => 'boolean',
        'is_sensitive' => 'boolean',
    ];
}
