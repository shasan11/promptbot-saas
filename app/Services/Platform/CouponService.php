<?php

namespace App\Services\Platform;

use App\Models\Coupon;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function apply(Subscription $subscription, string $code): Coupon
    {
        return DB::transaction(function () use ($subscription, $code): Coupon {
            $coupon = Coupon::whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])->lockForUpdate()->first();
            if (! $coupon || ! $coupon->isAvailable()) throw ValidationException::withMessages(['code' => 'This coupon is invalid, expired, or exhausted.']);
            if ($coupon->plans()->exists() && ! $coupon->plans()->whereKey($subscription->plan_id)->exists()) {
                throw ValidationException::withMessages(['code' => 'This coupon is not valid for the current plan.']);
            }
            if ($subscription->coupon_id === $coupon->id) return $coupon;
            if ($subscription->coupon_id) throw ValidationException::withMessages(['code' => 'Remove the current coupon before applying another.']);
            if ($coupon->billing_interval && $coupon->billing_interval !== $subscription->billing_interval) {
                throw ValidationException::withMessages(['code' => 'This coupon is not valid for the selected billing interval.']);
            }
            if ($coupon->per_account_limit) {
                $redeemed = $coupon->redemptions()->where('customer_account_id', $subscription->customer_account_id)->where('status', 'redeemed')->count();
                $reserved = Subscription::where('customer_account_id', $subscription->customer_account_id)->where('coupon_id', $coupon->id)->count();
                if (($redeemed + $reserved) >= $coupon->per_account_limit) {
                    throw ValidationException::withMessages(['code' => 'This account has reached the coupon redemption limit.']);
                }
            }

            $coupon->increment('redemptions');
            $subscription->update(['coupon_id' => $coupon->id]);
            return $coupon->refresh();
        });
    }

    public function remove(Subscription $subscription): void
    {
        $subscription->update(['coupon_id' => null]);
    }
}
