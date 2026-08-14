<?php

namespace App\Services\AI\Data;

final class ChatMessage
{
    public function __construct(
        public readonly string $role,
        public readonly string $content,
    ) {}
}
