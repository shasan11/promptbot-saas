<?php

namespace App\Services\AI;

use App\Services\AI\Exceptions\AIFeatureDisabledException;

class AIFeatureManager
{
    public function __construct(private readonly AIConfigResolver $config) {}

    /**
     * Layered gate: the platform master switch and the specific feature both
     * have to be on, AND — when the call originates from tenant context — the
     * Platform Owner must have separately allowed tenant AI access. A tenant
     * request never bypasses this by calling AIManager directly, since every
     * tenant-facing AI surface (e.g. Knowledge embeddings/answers) is expected
     * to route through this check before invoking AIManager.
     */
    public function isEnabled(string $featureKey): bool
    {
        if (! $this->config->isEnabled() || ! $this->config->isFeatureEnabled($featureKey)) {
            return false;
        }

        if (function_exists('tenancy') && tenancy()->initialized && ! $this->config->isTenantAiAllowed()) {
            return false;
        }

        return true;
    }

    /** @throws AIFeatureDisabledException */
    public function assertEnabled(string $featureKey): void
    {
        if ($this->isEnabled($featureKey)) {
            return;
        }

        if ($this->config->isEnabled() && $this->config->isFeatureEnabled($featureKey)
            && function_exists('tenancy') && tenancy()->initialized && ! $this->config->isTenantAiAllowed()) {
            throw AIFeatureDisabledException::tenantAiNotAllowed($featureKey);
        }

        throw AIFeatureDisabledException::forKey($featureKey);
    }
}
