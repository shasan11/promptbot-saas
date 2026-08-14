<?php

namespace App\Services\AI;

use App\Models\AiProvider;
use App\Services\Platform\PlatformSettingsService;

/**
 * Thin typed read layer over PlatformSettingsService's `ai`/`ai_features`
 * groups. Every other AI class depends on this rather than reading
 * PlatformSettingsService directly, so there is one seam to stub in tests.
 */
class AIConfigResolver
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function isEnabled(): bool
    {
        return (bool) ($this->settings->get('ai', 'enabled', false));
    }

    public function isFallbackEnabled(): bool
    {
        return (bool) ($this->settings->get('ai', 'fallback_enabled', true));
    }

    public function timeoutSeconds(?AiProvider $provider = null): int
    {
        return (int) ($provider?->timeout_seconds ?? $this->settings->get('ai', 'request_timeout_seconds', 30));
    }

    public function maxRetries(?AiProvider $provider = null): int
    {
        return (int) ($provider?->max_retries ?? $this->settings->get('ai', 'max_retries', 2));
    }

    public function isLoggingEnabled(): bool
    {
        return (bool) ($this->settings->get('ai', 'log_requests', true));
    }

    public function isCircuitBreakerEnabled(): bool
    {
        return (bool) ($this->settings->get('ai', 'circuit_breaker_enabled', true));
    }

    public function circuitBreakerFailureThreshold(): int
    {
        return (int) ($this->settings->get('ai', 'circuit_breaker_failure_threshold', 5));
    }

    public function circuitBreakerCooldownSeconds(): int
    {
        return (int) ($this->settings->get('ai', 'circuit_breaker_cooldown_seconds', 120));
    }

    public function isFeatureEnabled(string $featureKey): bool
    {
        return (bool) ($this->settings->get('ai_features', $featureKey, false));
    }

    public function isTenantAiAllowed(): bool
    {
        return (bool) ($this->settings->get('ai', 'allow_tenant_ai', false));
    }
}
