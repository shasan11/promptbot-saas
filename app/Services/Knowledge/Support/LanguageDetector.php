<?php

namespace App\Services\Knowledge\Support;

/**
 * Best-effort language detection with no external dependency.
 *
 * Script detection (Arabic, CJK, Devanagari, Cyrillic…) is reliable. Telling
 * apart languages that share the Latin alphabet is done with stop-word
 * frequency, which is good enough to tag a document but not authoritative — so
 * an explicit language chosen by the user always wins, and an inconclusive
 * result falls back to the knowledge base default rather than guessing.
 */
final class LanguageDetector
{
    /** @var array<string, array<int, string>> */
    private const STOP_WORDS = [
        'en' => ['the', 'and', 'that', 'have', 'for', 'not', 'with', 'you', 'this', 'but', 'from', 'they', 'will', 'would', 'there'],
        'es' => ['que', 'de', 'no', 'la', 'el', 'en', 'los', 'se', 'las', 'por', 'con', 'para', 'una', 'como', 'pero'],
        'fr' => ['le', 'de', 'un', 'et', 'les', 'des', 'est', 'pour', 'que', 'dans', 'qui', 'pas', 'sur', 'vous', 'avec'],
        'de' => ['der', 'die', 'und', 'den', 'von', 'zu', 'das', 'mit', 'sich', 'des', 'auf', 'für', 'ist', 'nicht', 'eine'],
        'pt' => ['que', 'não', 'uma', 'com', 'para', 'como', 'mais', 'dos', 'das', 'por', 'você', 'está', 'são', 'pelo'],
        'it' => ['che', 'di', 'il', 'la', 'per', 'una', 'con', 'non', 'sono', 'del', 'nel', 'come', 'più', 'anche'],
        'nl' => ['de', 'het', 'een', 'van', 'en', 'dat', 'niet', 'voor', 'met', 'zijn', 'op', 'aan', 'ook', 'maar'],
        'id' => ['yang', 'dan', 'di', 'untuk', 'dengan', 'ini', 'itu', 'dari', 'pada', 'tidak', 'akan', 'atau'],
    ];

    /** @var array<string, string> Unicode ranges that identify a language outright. */
    private const SCRIPTS = [
        'ar' => '/[\x{0600}-\x{06FF}]/u',
        'hi' => '/[\x{0900}-\x{097F}]/u',
        'ne' => '/[\x{0900}-\x{097F}]/u',
        'ru' => '/[\x{0400}-\x{04FF}]/u',
        'ja' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}]/u',
        'ko' => '/[\x{AC00}-\x{D7AF}]/u',
        'zh' => '/[\x{4E00}-\x{9FFF}]/u',
    ];

    public static function detect(string $text, ?string $fallback = null): ?string
    {
        $sample = mb_substr(trim($text), 0, 3000);

        if (mb_strlen($sample) < 20) {
            return $fallback;
        }

        // Script first. Japanese is checked before Chinese because Japanese text
        // contains Han characters too — matching zh first would misclassify it.
        foreach (self::SCRIPTS as $language => $pattern) {
            $matches = preg_match_all($pattern, $sample);

            if ($matches > 0 && ($matches / mb_strlen($sample)) > 0.1) {
                return $language;
            }
        }

        $words = preg_split('/\s+/u', mb_strtolower(preg_replace('/[^\p{L}\s]+/u', ' ', $sample) ?? '')) ?: [];
        $words = array_filter($words);

        if (count($words) < 10) {
            return $fallback;
        }

        $scores = [];

        foreach (self::STOP_WORDS as $language => $stopWords) {
            $scores[$language] = count(array_intersect($words, $stopWords));
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];

        // Require a real signal — a couple of incidental matches is noise, and
        // mislabelling a document's language degrades language-filtered
        // retrieval more than leaving it unset does.
        return $bestScore >= 3 ? $best : $fallback;
    }
}
