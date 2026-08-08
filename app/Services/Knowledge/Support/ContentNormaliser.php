<?php

namespace App\Services\Knowledge\Support;

/**
 * Cleans extracted text before it is hashed, chunked and embedded.
 *
 * Normalisation is what makes change detection work: a re-crawl that returns
 * the same prose with different line wrapping must produce the same hash, or
 * every sync re-embeds the whole site.
 */
final class ContentNormaliser
{
    public static function normalise(string $text): string
    {
        // Strip the UTF-8 BOM and normalise line endings before anything else.
        $text = preg_replace('/^\x{FEFF}/u', '', $text) ?? $text;
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Control characters (except tab and newline) come from binary files
        // that partially decoded; they break MySQL utf8mb4 inserts downstream.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        // Non-breaking and exotic spaces render identically but hash differently.
        $text = preg_replace('/[\x{00A0}\x{1680}\x{2000}-\x{200A}\x{202F}\x{205F}\x{3000}]/u', ' ', $text) ?? $text;
        // Zero-width characters are invisible and frequently used to smuggle
        // instructions past a human reviewer.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $text) ?? $text;

        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;
        // Collapse runs of blank lines to a single paragraph break.
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        }

        return trim($text);
    }

    /** Stable fingerprint of a document's content, used for change detection. */
    public static function hash(string $text): string
    {
        return hash('sha256', preg_replace('/\s+/u', ' ', mb_strtolower(trim($text))) ?? '');
    }
}
