<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Exceptions\Knowledge\ExtractionException;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Support\ContentNormaliser;

/**
 * Pure-PHP PDF text extraction.
 *
 * Scope, stated plainly: this reads text-based PDFs whose content streams are
 * uncompressed or Flate-compressed and whose fonts use standard encodings —
 * which covers the overwhelming majority of business documents (anything
 * exported from Word, Google Docs, or a reporting tool). It does NOT handle
 * scanned images (there is no text to find — that is OCR's job) or PDFs using
 * custom CID font encodings without a usable ToUnicode map.
 *
 * The important design choice is what happens when it cannot cope: rather than
 * emit mojibake into the knowledge base — where it would be embedded, indexed,
 * and eventually quoted back at a customer as if it were real content — it
 * checks its own output for legibility and fails loudly so the pipeline can
 * route to OCR or surface an actionable error.
 *
 * Installing smalot/pdfparser would broaden coverage; this class exists so the
 * module works without adding a dependency, and can be swapped by registering a
 * higher-priority DocumentExtractorInterface.
 */
class PdfExtractor implements DocumentExtractorInterface
{
    /** Below this share of sane characters, output is treated as garbage. */
    private const MIN_LEGIBLE_RATIO = 0.85;

    public function supports(string $mimeType, string $extension): bool
    {
        return $mimeType === 'application/pdf' || strtolower($extension) === 'pdf';
    }

    public function extract(string $path, string $originalFilename): ExtractedContent
    {
        $raw = @file_get_contents($path);

        if ($raw === false || ! str_starts_with($raw, '%PDF')) {
            throw ExtractionException::unreadable($originalFilename);
        }

        if ($this->isEncrypted($raw)) {
            throw new ExtractionException(
                "PDF {$originalFilename} is encrypted",
                \App\Enums\Knowledge\FailureCategory::InvalidFile,
                'This PDF is password-protected, so its text cannot be read. Upload an unprotected copy.',
            );
        }

        $pageCount = $this->countPages($raw);
        $pages = $this->extractPages($raw);

        $segments = [];
        $lines = [];

        foreach ($pages as $index => $pageText) {
            $clean = ContentNormaliser::normalise($pageText);

            if ($clean === '') {
                continue;
            }

            $pageNumber = $index + 1;
            $lines[] = $clean;
            // Page numbers are the whole point of citing a PDF, so they are
            // recorded per segment and ride through into chunk metadata.
            $segments[] = ['text' => $clean, 'page' => $pageNumber, 'type' => 'page'];
        }

        $text = ContentNormaliser::normalise(implode("\n\n", $lines));

        if ($text !== '' && ! $this->isLegible($text)) {
            throw new ExtractionException(
                "PDF {$originalFilename} decoded to unusable characters (non-standard font encoding)",
                \App\Enums\Knowledge\FailureCategory::ExtractionError,
                'This PDF uses a font encoding PromptBot cannot read, so the extracted text would be gibberish. '
                .'Re-export it as a standard PDF, or enable OCR in Knowledge settings and retry.',
            );
        }

        return new ExtractedContent(
            text: $text,
            segments: $segments,
            metadata: ['producer' => $this->metadataValue($raw, 'Producer')],
            pageCount: max($pageCount, count($segments)),
            hasTables: false,
            detectedTitle: $this->metadataValue($raw, 'Title'),
        );
    }

    public function priority(): int
    {
        return 40;
    }

    private function isEncrypted(string $raw): bool
    {
        return (bool) preg_match('/\/Encrypt\s+\d+\s+\d+\s+R/', $raw);
    }

    private function countPages(string $raw): int
    {
        if (preg_match('/\/Type\s*\/Pages\b[^>]*?\/Count\s+(\d+)/s', $raw, $matches)) {
            return (int) $matches[1];
        }

        return max(1, preg_match_all('#/Type\s*/Page[^s]#', $raw));
    }

    /**
     * Splits the file into content streams and decodes each one.
     *
     * @return array<int, string>
     */
    private function extractPages(string $raw): array
    {
        $pages = [];

        if (! preg_match_all('/stream\r?\n?(.*?)endstream/s', $raw, $matches)) {
            return [];
        }

        foreach ($matches[1] as $stream) {
            $decoded = $this->decodeStream($stream);

            if ($decoded === null) {
                continue;
            }

            // Only content streams carry text-showing operators; skip fonts,
            // images and metadata streams without paying to parse them.
            if (! str_contains($decoded, 'Tj') && ! str_contains($decoded, 'TJ')) {
                continue;
            }

            $text = $this->readTextOperators($decoded);

            if (trim($text) !== '') {
                $pages[] = $text;
            }
        }

        return $pages;
    }

    private function decodeStream(string $stream): ?string
    {
        $stream = trim($stream, "\r\n");

        // Try Flate first (by far the most common), then treat it as plain.
        $inflated = @gzuncompress($stream);

        if ($inflated === false) {
            $inflated = @gzinflate($stream);
        }

        if ($inflated !== false) {
            return $inflated;
        }

        return str_contains($stream, 'Tj') || str_contains($stream, 'TJ') ? $stream : null;
    }

    /**
     * Pulls text out of PDF text-showing operators.
     *
     *   (literal) Tj          — a single string
     *   [(a) -250 (b)] TJ     — an array of strings with kerning offsets
     *
     * Large negative kerning between array elements represents a word gap, so a
     * space is inserted; without that, "(Refund)-250(Policy)" would extract as
     * "RefundPolicy" and never match a search for either word.
     */
    private function readTextOperators(string $content): string
    {
        $output = [];

        // Text blocks are delimited by BT/ET; treat each as a line group.
        preg_match_all('/BT(.*?)ET/s', $content, $blocks);
        $sources = $blocks[1] ?: [$content];

        foreach ($sources as $block) {
            $line = '';

            preg_match_all('/\[(.*?)\]\s*TJ|\(((?:\\\\.|[^\\\\()])*)\)\s*Tj|T\*|\'|"/s', $block, $operators, PREG_SET_ORDER);

            foreach ($operators as $operator) {
                if (! empty($operator[1])) {
                    $line .= $this->readTjArray($operator[1]);

                    continue;
                }

                if (isset($operator[2])) {
                    $line .= $this->unescapeString($operator[2]);
                }
            }

            if (trim($line) !== '') {
                $output[] = trim($line);
            }
        }

        return implode("\n", $output);
    }

    private function readTjArray(string $array): string
    {
        preg_match_all('/\((?:\\\\.|[^\\\\()])*\)|-?\d+(?:\.\d+)?/s', $array, $parts);

        $line = '';

        foreach ($parts[0] as $part) {
            if (str_starts_with($part, '(')) {
                $line .= $this->unescapeString(substr($part, 1, -1));

                continue;
            }

            // Kerning is expressed in thousandths of an em, negated. Anything
            // beyond ~1/4 em of separation is a word break rather than tracking.
            if ((float) $part <= -250) {
                $line .= ' ';
            }
        }

        return $line;
    }

    private function unescapeString(string $value): string
    {
        $replacements = [
            '\\n' => "\n", '\\r' => "\r", '\\t' => "\t", '\\b' => "\x08",
            '\\f' => "\x0C", '\\(' => '(', '\\)' => ')', '\\\\' => '\\',
        ];

        $value = strtr($value, $replacements);

        // Octal escapes (\053) address bytes outside the printable range.
        $value = preg_replace_callback('/\\\\([0-7]{1,3})/', fn (array $m) => chr(octdec($m[1])), $value) ?? $value;

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }

    /**
     * Sanity check on decoded output. A PDF with a non-standard font encoding
     * decodes to a stream of accented punctuation that looks like text to
     * strlen() but is meaningless — and would poison retrieval if indexed.
     */
    private function isLegible(string $text): bool
    {
        $sample = mb_substr($text, 0, 2000);
        $total = mb_strlen($sample);

        if ($total === 0) {
            return false;
        }

        $sane = preg_match_all('/[\p{L}\p{N}\p{Zs}\p{P}\n]/u', $sample);

        if (($sane / $total) < self::MIN_LEGIBLE_RATIO) {
            return false;
        }

        // Real prose contains vowels and spaces. Their near-absence is the
        // clearest signal that a substitution cipher font defeated us.
        $letters = preg_match_all('/\p{L}/u', $sample);
        $vowels = preg_match_all('/[aeiouAEIOU\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Arabic}\p{Devanagari}\p{Cyrillic}]/u', $sample);

        return $letters === 0 || ($vowels / $letters) > 0.15;
    }

    private function metadataValue(string $raw, string $key): ?string
    {
        if (preg_match('/\/'.preg_quote($key, '/').'\s*\((?:\\\\.|[^\\\\()])*\)/', $raw, $matches)) {
            $value = trim($this->unescapeString(substr(strstr($matches[0], '(') ?: '', 1, -1)));

            return $value !== '' ? $value : null;
        }

        return null;
    }
}
