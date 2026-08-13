<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;

class AccountLimitService
{
    public function effective(CustomerAccount $account, string $featureKey, mixed $fallback = null, ?string $period = null): mixed
    {
        $limit = $account->limits()->where('feature_key', $featureKey)->where('is_enforced', true)
            ->when($period, fn ($query) => $query->where(fn ($inner) => $inner->whereNull('period')->orWhere('period', $period)))
            ->orderByRaw('CASE WHEN period IS NULL THEN 1 ELSE 0 END')->first();

        return $limit ? (float) $limit->limit_value : $fallback;
    }

    public function reached(CustomerAccount $account, string $featureKey, float $used, mixed $fallback = null, ?string $period = null): bool
    {
        $limit = $this->effective($account, $featureKey, $fallback, $period);
        return is_numeric($limit) && (float) $limit > 0 && $used >= (float) $limit;
    }
}
