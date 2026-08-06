<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WebsiteFooterLink extends Model
{
    use HasUuid;

    protected $fillable = ['label', 'url', 'group', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];
}
