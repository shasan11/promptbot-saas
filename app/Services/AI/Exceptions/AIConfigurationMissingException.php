<?php

namespace App\Services\AI\Exceptions;

class AIConfigurationMissingException extends AIException
{
    public static function masterDisabled(): self
    {
        return new self(
            'AI is disabled at the platform level.',
            'ai_configuration_missing',
            'AI has not been configured by the platform administrator.',
        );
    }

    public static function noModelsConfigured(string $purpose): self
    {
        return new self(
            "No enabled model is configured for purpose [{$purpose}].",
            'ai_configuration_missing',
            'AI has not been configured by the platform administrator.',
        );
    }
}
