<?php

namespace App\Services\AI\Data;

final class TestConnectionResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly int $latencyMs = 0,
    ) {}

    public static function success(string $message, int $latencyMs = 0): self
    {
        return new self(true, $message, $latencyMs);
    }

    public static function failure(string $message, int $latencyMs = 0): self
    {
        return new self(false, $message, $latencyMs);
    }
}
