<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Exceptions\Knowledge\ExtractionException;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Support\ContentNormaliser;

/**
 * Plain text, Markdown, CSV, JSON and XML.
 *
 * CSV and JSON get structural treatment rather than being dumped verbatim: a
 * raw CSV embeds as a wall of commas that matches nothing, whereas
 * "Column: value" rows retain the association between a header and its data.
 */
class PlainTextExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, ['text/plain', 'text/markdown', 'text/csv', 'application/json', 'application/xml', 'text/xml'], true)
            || in_array(strtolower($extension), ['txt', 'md', 'markdown', 'csv', 'json', 'xml'], true);
    }

    public function extract(string $path, string $originalFilename): ExtractedContent
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw ExtractionException::unreadable($originalFilename);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv' => $this->extractCsv($path, $originalFilename),
            'json' => $this->extractJson($raw, $originalFilename),
            'xml' => $this->extractXml($raw, $originalFilename),
            'md', 'markdown' => $this->extractMarkdown($raw),
            default => $this->extractPlain($raw),
        };
    }

    public function priority(): int
    {
        return 10;
    }

    private function extractPlain(string $raw): ExtractedContent
    {
        $text = ContentNormaliser::normalise($raw);

        return new ExtractedContent(
            text: $text,
            segments: [['text' => $text, 'type' => 'body']],
            pageCount: 1,
        );
    }

    /** Markdown keeps its headings so the heading-aware chunker has boundaries to use. */
    private function extractMarkdown(string $raw): ExtractedContent
    {
        $text = ContentNormaliser::normalise($raw);
        $segments = [];
        $currentHeading = null;
        $buffer = [];

        foreach (explode("\n", $text) as $line) {
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $matches)) {
                if ($buffer) {
                    $segments[] = ['text' => trim(implode("\n", $buffer)), 'heading' => $currentHeading, 'type' => 'section'];
                    $buffer = [];
                }

                $currentHeading = trim($matches[2]);
            }

            $buffer[] = $line;
        }

        if ($buffer) {
            $segments[] = ['text' => trim(implode("\n", $buffer)), 'heading' => $currentHeading, 'type' => 'section'];
        }

        return new ExtractedContent(
            text: $text,
            segments: $segments,
            pageCount: 1,
            hasTables: str_contains($text, '|---') || str_contains($text, '| ---'),
            detectedTitle: $this->firstHeading($text),
        );
    }

    private function extractCsv(string $path, string $originalFilename): ExtractedContent
    {
        $handle = @fopen($path, 'r');

        if ($handle === false) {
            throw ExtractionException::unreadable($originalFilename);
        }

        $header = null;
        $rows = [];
        $rowNumber = 0;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($row === [null] || $row === []) {
                    continue;
                }

                if ($header === null) {
                    $header = array_map(fn ($cell) => trim((string) $cell), $row);

                    continue;
                }

                $rowNumber++;
                $pairs = [];

                foreach ($row as $index => $cell) {
                    $label = $header[$index] ?? "Column {$index}";
                    $value = trim((string) $cell);

                    if ($value !== '') {
                        $pairs[] = "{$label}: {$value}";
                    }
                }

                if ($pairs) {
                    $rows[] = implode(' | ', $pairs);
                }
            }
        } finally {
            fclose($handle);
        }

        $text = ContentNormaliser::normalise(implode("\n", $rows));

        return new ExtractedContent(
            text: $text,
            segments: array_map(fn (string $row) => ['text' => $row, 'type' => 'row'], $rows),
            metadata: ['columns' => $header ?? [], 'row_count' => $rowNumber],
            pageCount: 1,
            hasTables: true,
        );
    }

    private function extractJson(string $raw, string $originalFilename): ExtractedContent
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw ExtractionException::unreadable($originalFilename);
        }

        $lines = [];
        $this->flattenJson($decoded, '', $lines);
        $text = ContentNormaliser::normalise(implode("\n", $lines));

        return new ExtractedContent(
            text: $text,
            segments: array_map(fn (string $line) => ['text' => $line, 'type' => 'field'], $lines),
            pageCount: 1,
        );
    }

    /**
     * Flattens nested JSON to "a.b.c: value" lines. The dotted path is kept
     * because it usually carries meaning ("pricing.enterprise.seats: 50" is
     * searchable in a way that a bare "50" is not).
     *
     * @param  array<int, string>  $lines
     */
    private function flattenJson(mixed $value, string $prefix, array &$lines, int $depth = 0): void
    {
        if ($depth > 12) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
                $this->flattenJson($child, $path, $lines, $depth + 1);
            }

            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        $scalar = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        $lines[] = $prefix === '' ? $scalar : "{$prefix}: {$scalar}";
    }

    private function extractXml(string $raw, string $originalFilename): ExtractedContent
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;
            // LIBXML_NONET forbids network fetches for external entities, and
            // LIBXML_NOENT is deliberately NOT passed so entities are left
            // unexpanded. This is an attacker-supplied document: expanding
            // entities is the XXE / billion-laughs foothold.
            $loaded = $document->loadXML($raw, LIBXML_NONET);

            if (! $loaded) {
                throw ExtractionException::unreadable($originalFilename);
            }

            $text = ContentNormaliser::normalise((string) $document->textContent);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return new ExtractedContent(
            text: $text,
            segments: [['text' => $text, 'type' => 'body']],
            pageCount: 1,
        );
    }

    private function firstHeading(string $text): ?string
    {
        return preg_match('/^#\s+(.+)$/m', $text, $matches) ? trim($matches[1]) : null;
    }
}
