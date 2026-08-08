<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusinessHourPolicy extends Model
{
    protected $fillable = ['name', 'timezone', 'is_default', 'status', 'created_by'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(BusinessHourInterval::class, 'policy_id')->orderBy('day_of_week')->orderBy('starts_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
