<?php

namespace App\Services\Knowledge\Data;

/**
 * The result of pulling readable text out of a source file.
 *
 * `segments` preserves whatever structure the extractor could recover — page
 * boundaries for PDFs, headings for Word and Markdown, sheet names for
 * spreadsheets. Chunking uses it to avoid splitting mid-section, and citations
 * use it to say "page 4" instead of "somewhere in this document".
 */
final class ExtractedContent
{
    /**
     * @param  array<int, array{text: string, page?: int, heading?: string, section?: string, type?: string}>  $segments
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $text,
        public readonly array $segments = [],
        public readonly array $metadata = [],
        public readonly int $pageCount = 0,
        public readonly bool $ocrApplied = false,
        public readonly bool $hasTables = false,
        public readonly ?string $detectedTitle = null,
    ) {}

    public static function empty(): self
    {
        return new self('');
    }

    public function isEmpty(): bool
    {
        return trim($this->text) === '';
    }

    public function characterCount(): int
    {
        return mb_strlen($this->text);
    }

    public function wordCount(): int
    {
        return str_word_count(strip_tags($this->text)) ?: count(preg_split('/\s+/u', trim($this->text)) ?: []);
    }

    /**
     * Whether this looks like a scanned document that yielded almost nothing —
     * the signal the pipeline uses to decide OCR is worth attempting.
     */
    public function looksLikeScannedDocument(): bool
    {
        if ($this->pageCount < 1) {
            return false;
        }

        $threshold = (int) config('knowledge.extraction.ocr_character_threshold_per_page');

        return ($this->characterCount() / $this->pageCount) < $threshold;
    }

    public function withText(string $text, bool $ocrApplied = false): self
    {
        return new self(
            $text,
            $this->segments,
            $this->metadata,
            $this->pageCount,
            $ocrApplied || $this->ocrApplied,
            $this->hasTables,
            $this->detectedTitle,
        );
    }
}
