<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\ChunkingStrategy;
use App\Services\Knowledge\Data\ChunkCandidate;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Support\TokenEstimator;

/**
 * Splits extracted content into retrieval units.
 *
 * Chunking quality sets the ceiling on search quality. Chunks that are too
 * large dilute token matches, while chunks that are too small lose context that
 * makes an answer usable ("30 days" is worthless without "refund window").
 *
 * Every strategy here therefore obeys the same two rules:
 *  - never split mid-sentence when a paragraph or heading boundary is available;
 *  - carry `overlap` tokens from the previous chunk so a fact spanning a
 *    boundary survives in at least one chunk intact.
 */
class ChunkingService
{
    /**
     * @param  array<string, mixed>  $baseMetadata  Citation fields copied onto every chunk.
     * @return array<int, ChunkCandidate>
     */
    public function chunk(
        ExtractedContent $content,
        ChunkingStrategy $strategy,
        int $chunkSize,
        int $overlap,
        array $baseMetadata = [],
    ): array {
        [$chunkSize, $overlap] = $this->clamp($chunkSize, $overlap);

        $candidates = match ($strategy) {
            ChunkingStrategy::Faq => $this->chunkAsSingleUnit($content, $baseMetadata),
            ChunkingStrategy::Heading, ChunkingStrategy::Markdown => $this->chunkBySegments($content, $chunkSize, $overlap, $baseMetadata),
            ChunkingStrategy::Paragraph => $this->chunkByParagraph($content, $chunkSize, $overlap, $baseMetadata),
            ChunkingStrategy::Semantic => $this->chunkSemantically($content, $chunkSize, $overlap, $baseMetadata),
            ChunkingStrategy::Code => $this->chunkCode($content, $chunkSize, $overlap, $baseMetadata),
            ChunkingStrategy::FixedToken => $this->chunkFixed($content->text, $chunkSize, $overlap, $baseMetadata),
        };

        return $this->reindex($candidates);
    }

    /**
     * Clamps tenant-supplied sizes to platform bounds. An overlap at or above
     * the chunk size would make the window never advance — an infinite loop
     * rather than a bad configuration.
     *
     * @return array{0: int, 1: int}
     */
    private function clamp(int $chunkSize, int $overlap): array
    {
        $min = (int) config('knowledge.chunking.min_chunk_size');
        $max = (int) config('knowledge.chunking.max_chunk_size');
        $maxRatio = (float) config('knowledge.chunking.max_overlap_ratio');

        $chunkSize = max($min, min($max, $chunkSize));
        $overlap = max(0, min((int) floor($chunkSize * $maxRatio), $overlap));

        return [$chunkSize, $overlap];
    }

    /** FAQs and short manual notes are one chunk — splitting them separates question from answer. */
    private function chunkAsSingleUnit(ExtractedContent $content, array $baseMetadata): array
    {
        $text = trim($content->text);

        if ($text === '') {
            return [];
        }

        // An unusually long FAQ answer still gets split, or it would blow the
        // context budget on its own.
        $limit = (int) config('knowledge.chunking.max_chunk_size');

        if (TokenEstimator::estimate($text) <= $limit) {
            return [new ChunkCandidate(0, $text, $baseMetadata, TokenEstimator::estimate($text))];
        }

        return $this->chunkFixed($text, $limit, 32, $baseMetadata);
    }

    /**
     * Heading/structure-aware: each extractor segment (a PDF page, a Word
     * heading section, an HTML block) is packed into chunks without crossing
     * into a segment under a different heading.
     */
    private function chunkBySegments(ExtractedContent $content, int $chunkSize, int $overlap, array $baseMetadata): array
    {
        if (! $content->segments) {
            return $this->chunkByParagraph($content, $chunkSize, $overlap, $baseMetadata);
        }

        $candidates = [];
        $buffer = [];
        $bufferTokens = 0;
        $currentContext = null;

        $flush = function () use (&$buffer, &$bufferTokens, &$candidates, &$currentContext, $baseMetadata): void {
            if (! $buffer) {
                return;
            }

            $candidates[] = new ChunkCandidate(
                count($candidates),
                trim(implode("\n\n", $buffer)),
                array_merge($baseMetadata, array_filter($currentContext ?? [], fn ($v) => $v !== null && $v !== '')),
                $bufferTokens,
            );

            $buffer = [];
            $bufferTokens = 0;
        };

        foreach ($content->segments as $segment) {
            $text = trim((string) ($segment['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $context = array_filter([
                'page' => $segment['page'] ?? null,
                'heading' => $segment['heading'] ?? null,
                'section' => $segment['section'] ?? null,
            ], fn ($v) => $v !== null);

            // A new heading or page starts a new chunk: mixing two topics into
            // one embedding is what makes retrieval return "almost right".
            if ($currentContext !== null && $context !== $currentContext && $buffer) {
                $flush();
            }

            $currentContext = $context;
            $tokens = TokenEstimator::estimate($text);

            // A single oversized segment (a long PDF page) is split on its own.
            if ($tokens > $chunkSize) {
                $flush();

                foreach ($this->chunkFixed($text, $chunkSize, $overlap, array_merge($baseMetadata, $context)) as $piece) {
                    $candidates[] = new ChunkCandidate(count($candidates), $piece->content, $piece->metadata, $piece->tokenCount);
                }

                continue;
            }

            if ($bufferTokens + $tokens > $chunkSize) {
                $flush();
            }

            $buffer[] = $text;
            $bufferTokens += $tokens;
        }

        $flush();

        return $candidates;
    }

    private function chunkByParagraph(ExtractedContent $content, int $chunkSize, int $overlap, array $baseMetadata): array
    {
        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\n{2,}/', $content->text) ?: []),
            fn (string $p) => $p !== ''
        ));

        return $this->packUnits($paragraphs, $chunkSize, $overlap, $baseMetadata, "\n\n");
    }

    /**
     * Semantic chunking, lexical-overlap flavour.
     *
     * True semantic chunking embeds every sentence and cuts where adjacent
     * similarity drops — accurate, but it doubles the embedding bill for every
     * document. This approximates the same boundary using vocabulary overlap
     * between consecutive sentences, which costs nothing and catches the
     * obvious topic shifts. Documented as an approximation rather than sold as
     * the real thing.
     */
    private function chunkSemantically(ExtractedContent $content, int $chunkSize, int $overlap, array $baseMetadata): array
    {
        $sentences = $this->splitSentences($content->text);

        if (! $sentences) {
            return [];
        }

        $groups = [];
        $current = [array_shift($sentences)];

        foreach ($sentences as $sentence) {
            $similarity = $this->lexicalOverlap(end($current) ?: '', $sentence);
            $projected = TokenEstimator::estimate(implode(' ', [...$current, $sentence]));

            // Break on a clear topic shift, or when the group is already full.
            if (($similarity < 0.08 && count($current) >= 2) || $projected > $chunkSize) {
                $groups[] = implode(' ', $current);
                $current = [];
            }

            $current[] = $sentence;
        }

        if ($current) {
            $groups[] = implode(' ', $current);
        }

        return $this->packUnits($groups, $chunkSize, $overlap, $baseMetadata, ' ');
    }

    /** Code splits on top-level declarations rather than mid-statement. */
    private function chunkCode(ExtractedContent $content, int $chunkSize, int $overlap, array $baseMetadata): array
    {
        $pattern = '/^(?=\s*(?:(?:public|private|protected|static|async|export|def|func|fn)\s+)*'
            .'(?:function|class|interface|trait|enum|struct|impl|def|func|fn)\s)/m';

        $units = array_values(array_filter(
            array_map('trim', preg_split($pattern, $content->text) ?: []),
            fn (string $u) => $u !== ''
        ));

        return $units
            ? $this->packUnits($units, $chunkSize, $overlap, $baseMetadata, "\n\n")
            : $this->chunkFixed($content->text, $chunkSize, $overlap, $baseMetadata);
    }

    /**
     * Packs pre-split units into chunks up to the size budget, carrying the tail
     * of the previous chunk forward as overlap.
     *
     * @param  array<int, string>  $units
     * @return array<int, ChunkCandidate>
     */
    private function packUnits(array $units, int $chunkSize, int $overlap, array $baseMetadata, string $glue): array
    {
        $candidates = [];
        $buffer = [];
        $bufferTokens = 0;

        foreach ($units as $unit) {
            $tokens = TokenEstimator::estimate($unit);

            if ($tokens > $chunkSize) {
                if ($buffer) {
                    $candidates[] = new ChunkCandidate(count($candidates), implode($glue, $buffer), $baseMetadata, $bufferTokens);
                    $buffer = [];
                    $bufferTokens = 0;
                }

                foreach ($this->chunkFixed($unit, $chunkSize, $overlap, $baseMetadata) as $piece) {
                    $candidates[] = new ChunkCandidate(count($candidates), $piece->content, $piece->metadata, $piece->tokenCount);
                }

                continue;
            }

            if ($bufferTokens + $tokens > $chunkSize && $buffer) {
                $candidates[] = new ChunkCandidate(count($candidates), implode($glue, $buffer), $baseMetadata, $bufferTokens);

                $buffer = $this->overlapTail($buffer, $overlap);
                $bufferTokens = TokenEstimator::estimate(implode($glue, $buffer));
            }

            $buffer[] = $unit;
            $bufferTokens += $tokens;
        }

        if ($buffer) {
            $candidates[] = new ChunkCandidate(count($candidates), implode($glue, $buffer), $baseMetadata, $bufferTokens);
        }

        return $candidates;
    }

    /**
     * Trailing units worth up to `$overlap` tokens, kept as the head of the next
     * chunk.
     *
     * @param  array<int, string>  $buffer
     * @return array<int, string>
     */
    private function overlapTail(array $buffer, int $overlap): array
    {
        if ($overlap <= 0) {
            return [];
        }

        $tail = [];
        $tokens = 0;

        foreach (array_reverse($buffer) as $unit) {
            $unitTokens = TokenEstimator::estimate($unit);

            if ($tokens + $unitTokens > $overlap && $tail) {
                break;
            }

            array_unshift($tail, $unit);
            $tokens += $unitTokens;
        }

        return $tail;
    }

    /**
     * Last-resort splitter: a sliding character window that still prefers to cut
     * at a sentence or word boundary within the last 20% of the window.
     *
     * @return array<int, ChunkCandidate>
     */
    private function chunkFixed(string $text, int $chunkSize, int $overlap, array $baseMetadata): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $windowChars = TokenEstimator::charactersFor($chunkSize);
        $overlapChars = TokenEstimator::charactersFor($overlap);
        $candidates = [];
        $position = 0;
        $length = mb_strlen($text);

        while ($position < $length) {
            $slice = mb_substr($text, $position, $windowChars);

            if (mb_strlen($slice) === $windowChars && $position + $windowChars < $length) {
                $slice = $this->trimToBoundary($slice);
            }

            $slice = trim($slice);

            if ($slice !== '') {
                $candidates[] = new ChunkCandidate(count($candidates), $slice, $baseMetadata, TokenEstimator::estimate($slice));
            }

            // Advance by at least one character even in pathological cases, so
            // the loop always terminates.
            $advance = max(1, mb_strlen($slice) - $overlapChars);
            $position += $advance;
        }

        return $candidates;
    }

    private function trimToBoundary(string $slice): string
    {
        $searchFrom = (int) (mb_strlen($slice) * 0.8);
        $tail = mb_substr($slice, $searchFrom);

        if (preg_match('/^(.*[.!?۔。！？])\s/su', $tail, $matches)) {
            return mb_substr($slice, 0, $searchFrom).$matches[1];
        }

        $lastSpace = mb_strrpos($slice, ' ');

        return $lastSpace !== false && $lastSpace > $searchFrom ? mb_substr($slice, 0, $lastSpace) : $slice;
    }

    /** @return array<int, string> */
    private function splitSentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?۔。！？])\s+(?=[\p{Lu}\p{Han}\p{Hiragana}])/u', $text) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $s) => $s !== ''));
    }

    /** Jaccard overlap of the two sentences' vocabularies. */
    private function lexicalOverlap(string $a, string $b): float
    {
        $tokenise = static function (string $text): array {
            $words = preg_split('/\W+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_unique(array_filter($words, fn (string $w) => mb_strlen($w) > 3));
        };

        $left = $tokenise($a);
        $right = $tokenise($b);

        if (! $left || ! $right) {
            return 0.0;
        }

        $union = count(array_unique(array_merge($left, $right)));

        return $union === 0 ? 0.0 : count(array_intersect($left, $right)) / $union;
    }

    /**
     * Reassigns sequential indexes. Strategies that delegate to a sub-splitter
     * can otherwise emit duplicates, which would collide on the
     * (owner_key, chunk_index) unique constraint at insert time.
     *
     * @param  array<int, ChunkCandidate>  $candidates
     * @return array<int, ChunkCandidate>
     */
    private function reindex(array $candidates): array
    {
        $result = [];

        foreach (array_values($candidates) as $index => $candidate) {
            $result[] = new ChunkCandidate($index, $candidate->content, $candidate->metadata, $candidate->tokenCount);
        }

        return $result;
    }
}
