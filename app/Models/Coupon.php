<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use HasUuid;
    protected $guarded = [];
    protected $casts = ['value' => 'decimal:2', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'is_active' => 'boolean', 'metadata' => 'array'];
    public function plans(): BelongsToMany { return $this->belongsToMany(Plan::class, 'coupon_plans'); }
    public function redemptions(): HasMany { return $this->hasMany(CouponRedemption::class); }
    public function isAvailable(): bool { return $this->is_active && (! $this->starts_at || $this->starts_at->isPast()) && (! $this->expires_at || $this->expires_at->isFuture()) && ($this->max_redemptions === null || $this->redemptions < $this->max_redemptions); }
}
