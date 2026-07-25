<?php

namespace App\Services\Platform;

use App\Models\Tenant;

class TenantMetricsService
{
    public function summary(): array
    {
        return [
            'total' => Tenant::query()->count(),
            'active' => Tenant::query()->where('status', 'active')->count(),
            'trial' => Tenant::query()->whereHas('subscriptions', fn ($query) => $query->where('status', 'trial'))->count(),
            'suspended' => Tenant::query()->where('status', 'suspended')->count(),
            'recent' => Tenant::query()->with(['plan', 'domains'])->latest()->limit(8)->get(),
        ];
    }
}
