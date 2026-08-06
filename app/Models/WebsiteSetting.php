<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    use HasUuid;

    protected $fillable = ['group', 'key', 'value', 'encrypted', 'is_sensitive'];

    protected $casts = [
        'value' => 'array',
        'encrypted' => 'boolean',
        'is_sensitive' => 'boolean',
    ];
}
