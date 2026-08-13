<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteFormSubmission extends Model
{
    use HasUuid;
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'utm' => 'array'];
    protected $hidden = ['ip_hash'];
    public function form(): BelongsTo { return $this->belongsTo(WebsiteForm::class, 'website_form_id'); }
}
