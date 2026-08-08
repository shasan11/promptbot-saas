<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessHourInterval extends Model
{
    protected $fillable = ['policy_id', 'day_of_week', 'starts_at', 'ends_at'];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(BusinessHourPolicy::class, 'policy_id');
    }
}
