<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class WebsiteRedirect extends Model
{
    use HasUuid;
    protected $fillable = ['from_path', 'to_url', 'status_code', 'is_active', 'hit_count'];
    protected $casts = ['is_active' => 'boolean', 'status_code' => 'integer', 'hit_count' => 'integer'];
}
