<?php

namespace App\Services\AI\Providers;

class OpenRouterProvider extends AbstractOpenAiCompatibleProvider
{
    public function driverKey(): string
    {
        return 'openrouter';
    }
}
