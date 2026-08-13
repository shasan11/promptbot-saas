<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WebsiteTag extends Model
{
    use HasUuid;
    protected $guarded = [];
}
