<?php

namespace App\Enums\Knowledge;

enum SourcePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Authoritative = 'authoritative';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Normal => 'Normal',
            self::High => 'High',
            self::Authoritative => 'Authoritative',
        };
    }

    /**
     * Multiplier applied during re-ranking. Kept deliberately close to 1.0 so
     * priority nudges ordering between comparably relevant chunks rather than
     * dragging an irrelevant authoritative document to the top.
     */
    public function rankingWeight(): float
    {
        return match ($this) {
            self::Low => 0.92,
            self::Normal => 1.0,
            self::High => 1.06,
            self::Authoritative => 1.12,
        };
    }
}
