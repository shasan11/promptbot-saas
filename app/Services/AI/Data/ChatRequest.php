<?php

namespace App\Services\AI\Data;

use App\Enums\AI\AIPurpose;

final class ChatRequest
{
    /** @param  array<int, ChatMessage>  $messages */
    public function __construct(
        public readonly array $messages,
        public readonly AIPurpose $purpose = AIPurpose::General,
        public readonly ?float $temperature = null,
        public readonly ?int $maxTokens = null,
        public readonly bool $jsonMode = false,
    ) {}
}
