<?php

namespace App\Services\AI\Data;

final class ChatResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly float $estimatedCost = 0.0,
        public readonly int $latencyMs = 0,
        public readonly ?string $finishReason = null,
    ) {}
}
