<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;

class CouponRedemption extends Model
{
    use HasUuid;
    protected $guarded = [];
    protected $casts = ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime'];
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function subscription() { return $this->belongsTo(Subscription::class); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
