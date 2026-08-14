<?php

namespace App\Services\AI\Contracts;

use App\Models\AiModel;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbedRequest;
use App\Services\AI\Data\EmbedResult;
use App\Services\AI\Data\TestConnectionResult;

interface AIProviderDriverInterface
{
    /** @throws \App\Services\AI\Exceptions\AIException */
    public function chat(ChatRequest $request, AiModel $model): ChatResult;

    /** @throws \App\Services\AI\Exceptions\AIException */
    public function embed(EmbedRequest $request, AiModel $model): EmbedResult;

    public function testConnection(): TestConnectionResult;

    public function driverKey(): string;
}
