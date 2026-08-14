<?php

namespace App\Services\AI\Exceptions;

class AIFeatureDisabledException extends AIException
{
    public static function forKey(string $featureKey): self
    {
        return new self(
            "AI feature [{$featureKey}] is disabled.",
            'feature_disabled',
            'This AI feature is currently disabled by the platform administrator.',
        );
    }

    public static function tenantAiNotAllowed(string $featureKey): self
    {
        return new self(
            "AI feature [{$featureKey}] was requested from tenant context, but tenant AI access is disabled.",
            'feature_disabled',
            'AI access has not been enabled for tenants by the platform administrator.',
        );
    }
}
