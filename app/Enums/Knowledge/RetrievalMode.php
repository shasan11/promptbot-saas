<?php

namespace App\Enums\Knowledge;

enum RetrievalMode: string
{
    case Semantic = 'semantic';
    case Keyword = 'keyword';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Semantic => 'Semantic',
            self::Keyword => 'Keyword',
            self::Hybrid => 'Hybrid',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Semantic => 'Matches on meaning. Finds answers phrased differently from the question.',
            self::Keyword => 'Matches on exact words. Best for product codes, error codes and names.',
            self::Hybrid => 'Runs both and merges the rankings. Recommended for most knowledge bases.',
        };
    }

    public function usesVectors(): bool
    {
        return $this !== self::Keyword;
    }

    public function usesKeywords(): bool
    {
        return $this !== self::Semantic;
    }
}
