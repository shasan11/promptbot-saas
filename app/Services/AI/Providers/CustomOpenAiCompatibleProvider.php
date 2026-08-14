<?php

namespace App\Services\AI\Providers;

class CustomOpenAiCompatibleProvider extends AbstractOpenAiCompatibleProvider
{
    public function driverKey(): string
    {
        return 'custom';
    }
}
