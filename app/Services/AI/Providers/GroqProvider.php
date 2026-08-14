<?php

namespace App\Services\AI\Providers;

class GroqProvider extends AbstractOpenAiCompatibleProvider
{
    public function driverKey(): string
    {
        return 'groq';
    }
}
