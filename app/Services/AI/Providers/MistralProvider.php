<?php

namespace App\Services\AI\Providers;

class MistralProvider extends AbstractOpenAiCompatibleProvider
{
    public function driverKey(): string
    {
        return 'mistral';
    }
}
