<?php

namespace App\Services\Knowledge\Support;

/**
 * Approximates token counts without pulling in a tokenizer.
 *
 * Every consumer of this class uses it for *budgeting* — chunk sizes, context
 * windows, cost projections — never for anything that must be exact. The
 * character-per-token ratio is configurable because it varies by script: ~4 for
 * English and closer to 1.5 for CJK. It is used only for deterministic local
 * processing limits and approximate usage reporting.
 */
final class TokenEstimator
{
    public static function estimate(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $ratio = max(1, (int) config('knowledge.chunking.characters_per_token', 4));

        // CJK text carries far more information per character, so the Latin
        // ratio would understate its token count by roughly half.
        if (self::isDenseScript($text)) {
            $ratio = max(1, (int) round($ratio / 2.5));
        }

        return (int) max(1, ceil(mb_strlen($text) / $ratio));
    }

    public static function charactersFor(int $tokens): int
    {
        return $tokens * max(1, (int) config('knowledge.chunking.characters_per_token', 4));
    }

    private static function isDenseScript(string $text): bool
    {
        $sample = mb_substr($text, 0, 400);
        $cjk = preg_match_all('/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $sample);

        return $cjk > 0 && ($cjk / max(1, mb_strlen($sample))) > 0.2;
    }
}
