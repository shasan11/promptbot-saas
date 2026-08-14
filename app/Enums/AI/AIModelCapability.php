<?php

namespace App\Enums\AI;

enum AIModelCapability: string
{
    case Chat = 'chat';
    case Embedding = 'embedding';

    public function label(): string
    {
        return match ($this) {
            self::Chat => 'Chat',
            self::Embedding => 'Embedding',
        };
    }
}
