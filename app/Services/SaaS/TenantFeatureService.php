<?php

namespace App\Services\SaaS;

use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantFeatureService
{
    public function enabled(string $feature): bool
    {
        $config = $this->featureConfig($feature);

        return (bool) ($config['enabled'] ?? false);
    }

    public function limit(string $feature): ?int
    {
        $config = $this->featureConfig($feature);

        if (! $this->enabled($feature) || ($config['unlimited'] ?? false)) {
            return null;
        }

        return isset($config['limit']) ? (int) $config['limit'] : null;
    }

    public function usage(string $feature): int
    {
        return (int) Cache::get($this->usageKey($feature), 0);
    }

    public function canConsume(string $feature, int $amount = 1): bool
    {
        if (! $this->enabled($feature)) {
            return false;
        }

        $limit = $this->limit($feature);

        return $limit === null || ($this->usage($feature) + $amount) <= $limit;
    }

    public function remaining(string $feature): ?int
    {
        $limit = $this->limit($feature);

        return $limit === null ? null : max(0, $limit - $this->usage($feature));
    }

    protected function featureConfig(string $feature): array
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return ['enabled' => false];
        }

        $override = $tenant->featureOverrides()
            ->whereHas('feature', fn ($query) => $query->where('code', $feature))
            ->with('feature')
            ->first();

        if ($override) {
            return [
                'enabled' => $override->enabled ?? true,
                'limit' => $override->limit,
                'unlimited' => $override->unlimited,
            ];
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['trial', 'active', 'manual'])
            ->latest()
            ->with('plan.features')
            ->first();

        $pivot = $subscription?->plan?->features->firstWhere('code', $feature)?->pivot;

        return [
            'enabled' => (bool) ($pivot?->enabled ?? false),
            'limit' => $pivot?->limit,
            'unlimited' => (bool) ($pivot?->unlimited ?? false),
        ];
    }

    protected function usageKey(string $feature): string
    {
        return sprintf('tenant:%s:feature:%s:usage', tenant('id'), $feature);
    }
}
