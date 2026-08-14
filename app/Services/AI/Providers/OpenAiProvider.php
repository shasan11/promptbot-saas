<?php

namespace App\Services\AI\Providers;

class OpenAiProvider extends AbstractOpenAiCompatibleProvider
{
    public function driverKey(): string
    {
        return 'openai';
    }
}
