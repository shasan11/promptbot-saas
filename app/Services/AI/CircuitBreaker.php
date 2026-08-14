<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed per-provider circuit breaker. After N consecutive failures a
 * provider is skipped for a cooldown window rather than retried on every
 * request, so a dead provider doesn't add its full timeout to every call.
 */
class CircuitBreaker
{
    public function __construct(private readonly AIConfigResolver $config) {}

    public function isOpen(string $providerId): bool
    {
        if (! $this->config->isCircuitBreakerEnabled()) {
            return false;
        }

        return (bool) Cache::get($this->openKey($providerId), false);
    }

    public function recordFailure(string $providerId): void
    {
        if (! $this->config->isCircuitBreakerEnabled()) {
            return;
        }

        $failures = (int) Cache::get($this->failureKey($providerId), 0) + 1;
        Cache::put($this->failureKey($providerId), $failures, now()->addMinutes(30));

        if ($failures >= $this->config->circuitBreakerFailureThreshold()) {
            Cache::put($this->openKey($providerId), true, now()->addSeconds($this->config->circuitBreakerCooldownSeconds()));
        }
    }

    public function recordSuccess(string $providerId): void
    {
        Cache::forget($this->failureKey($providerId));
        Cache::forget($this->openKey($providerId));
    }

    private function failureKey(string $providerId): string
    {
        return "ai:circuit:{$providerId}:failures";
    }

    private function openKey(string $providerId): string
    {
        return "ai:circuit:{$providerId}:open";
    }
}
