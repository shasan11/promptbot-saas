<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteForm extends Model
{
    use HasUuid;
    protected $guarded = [];
    protected $casts = ['fields' => 'array', 'is_active' => 'boolean'];
    public function submissions(): HasMany { return $this->hasMany(WebsiteFormSubmission::class); }
}
