<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Exceptions\Knowledge\ExtractionException;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Support\ContentNormaliser;
use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * DOCX, XLSX and PPTX — the Office Open XML family.
 *
 * These are ZIP containers of XML, so they are readable with ext-zip and
 * ext-dom alone; no third-party parser is needed and none is installed. Every
 * archive is checked against the zip-bomb guard before a single entry is read.
 *
 * Legacy binary .doc/.xls/.ppt are a different, undocumented format and are NOT
 * handled here — they are rejected with an actionable message telling the user
 * to re-save as the modern format.
 */
class OfficeOpenXmlExtractor implements DocumentExtractorInterface
{
    private const NS_WORD = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_SHEET = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    public function supports(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ], true) || in_array(strtolower($extension), ['docx', 'xlsx', 'pptx'], true);
    }

    public function extract(string $path, string $originalFilename): ExtractedContent
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw ExtractionException::unreadable($originalFilename);
        }

        try {
            $this->assertArchiveIsSafe($zip, $originalFilename);

            return match ($extension) {
                'docx' => $this->extractWord($zip),
                'xlsx' => $this->extractSpreadsheet($zip),
                'pptx' => $this->extractPresentation($zip),
                default => throw ExtractionException::unsupported($extension, $originalFilename),
            };
        } finally {
            $zip->close();
        }
    }

    public function priority(): int
    {
        return 30;
    }

    /**
     * Zip-bomb guard. An OOXML file is attacker-controlled input: a few hundred
     * kilobytes can expand to gigabytes and take the worker down with it. Caps
     * come from config so an operator can loosen them for genuinely large
     * internal documents.
     */
    private function assertArchiveIsSafe(ZipArchive $zip, string $filename): void
    {
        $guard = (array) config('knowledge.uploads.archive_guard');

        if ($zip->numFiles > $guard['max_entries']) {
            throw ExtractionException::archiveGuardTripped($filename, "{$zip->numFiles} entries exceeds the limit");
        }

        $uncompressed = 0;
        $compressed = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if ($stat === false) {
                continue;
            }

            // A path escaping the archive root is never legitimate here.
            if (str_contains((string) $stat['name'], '..')) {
                throw ExtractionException::archiveGuardTripped($filename, 'archive contains a traversal path');
            }

            $uncompressed += (int) $stat['size'];
            $compressed += (int) $stat['comp_size'];

            if ($uncompressed > $guard['max_uncompressed_bytes']) {
                throw ExtractionException::archiveGuardTripped($filename, 'uncompressed size exceeds the limit');
            }
        }

        if ($compressed > 0 && ($uncompressed / $compressed) > $guard['max_compression_ratio']) {
            throw ExtractionException::archiveGuardTripped($filename, 'compression ratio looks like a zip bomb');
        }
    }

    private function extractWord(ZipArchive $zip): ExtractedContent
    {
        $xml = $zip->getFromName('word/document.xml');

        if ($xml === false) {
            throw ExtractionException::unreadable('word/document.xml');
        }

        $xpath = $this->xpathFor($xml, ['w' => self::NS_WORD]);
        $segments = [];
        $lines = [];
        $currentHeading = null;
        $hasTables = ($xpath->query('//w:tbl')?->length ?? 0) > 0;

        foreach ($xpath->query('//w:body/w:p | //w:body/w:tbl') ?: [] as $block) {
            if ($block->localName === 'tbl') {
                $table = $this->renderWordTable($xpath, $block);

                if ($table !== '') {
                    $lines[] = $table;
                    $segments[] = ['text' => $table, 'heading' => $currentHeading, 'type' => 'table'];
                }

                continue;
            }

            $text = trim((string) $block->textContent);

            if ($text === '') {
                continue;
            }

            // Word marks headings with a paragraph style named Heading1..9;
            // recovering them is what lets heading-aware chunking work on DOCX.
            $styleNode = $xpath->query('.//w:pPr/w:pStyle/@w:val', $block)?->item(0);
            $style = $styleNode?->nodeValue ?? '';

            if (preg_match('/^Heading[1-9]$/i', $style) || strcasecmp($style, 'Title') === 0) {
                $currentHeading = $text;
                $segments[] = ['text' => $text, 'heading' => $text, 'type' => 'heading'];
            } else {
                $segments[] = ['text' => $text, 'heading' => $currentHeading, 'type' => 'paragraph'];
            }

            $lines[] = $text;
        }

        return new ExtractedContent(
            text: ContentNormaliser::normalise(implode("\n\n", $lines)),
            segments: $segments,
            pageCount: 1,
            hasTables: $hasTables,
            detectedTitle: $segments[0]['type'] === 'heading' ? $segments[0]['text'] : null,
        );
    }

    private function renderWordTable(DOMXPath $xpath, \DOMNode $table): string
    {
        $rows = [];

        foreach ($xpath->query('.//w:tr', $table) ?: [] as $tr) {
            $cells = [];

            foreach ($xpath->query('.//w:tc', $tr) ?: [] as $tc) {
                $cells[] = trim(preg_replace('/\s+/u', ' ', (string) $tc->textContent) ?? '');
            }

            if (array_filter($cells)) {
                $rows[] = '| '.implode(' | ', $cells).' |';
            }
        }

        if (! $rows) {
            return '';
        }

        $columns = substr_count($rows[0], '|') - 1;
        array_splice($rows, 1, 0, ['|'.str_repeat(' --- |', max(1, $columns))]);

        return implode("\n", $rows);
    }

    /**
     * XLSX stores most cell text in a shared-strings table with cells holding
     * only an index into it, so both parts must be read to recover anything.
     */
    private function extractSpreadsheet(ZipArchive $zip): ExtractedContent
    {
        $sharedStrings = $this->readSharedStrings($zip);
        $sheetNames = $this->readSheetNames($zip);

        $segments = [];
        $lines = [];
        $sheetIndex = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (! preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name)) {
                continue;
            }

            $sheetIndex++;
            $label = $sheetNames[$sheetIndex - 1] ?? "Sheet {$sheetIndex}";
            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            $xpath = $this->xpathFor($xml, ['s' => self::NS_SHEET]);
            $rows = [];

            foreach ($xpath->query('//s:sheetData/s:row') ?: [] as $row) {
                $cells = [];

                foreach ($xpath->query('.//s:c', $row) ?: [] as $cell) {
                    $type = $xpath->query('./@t', $cell)?->item(0)?->nodeValue;
                    $valueNode = $xpath->query('./s:v', $cell)?->item(0);
                    $inline = $xpath->query('./s:is', $cell)?->item(0);

                    $value = match (true) {
                        $type === 's' && $valueNode !== null => $sharedStrings[(int) $valueNode->nodeValue] ?? '',
                        $inline !== null => trim((string) $inline->textContent),
                        $valueNode !== null => trim((string) $valueNode->nodeValue),
                        default => '',
                    };

                    if ($value !== '') {
                        $cells[] = $value;
                    }
                }

                if ($cells) {
                    $rows[] = implode(' | ', $cells);
                }
            }

            if ($rows) {
                $block = "{$label}\n".implode("\n", $rows);
                $lines[] = $block;
                $segments[] = ['text' => $block, 'heading' => $label, 'section' => $label, 'type' => 'sheet'];
            }
        }

        return new ExtractedContent(
            text: ContentNormaliser::normalise(implode("\n\n", $lines)),
            segments: $segments,
            metadata: ['sheets' => $sheetNames],
            pageCount: max(1, $sheetIndex),
            hasTables: true,
        );
    }

    private function extractPresentation(ZipArchive $zip): ExtractedContent
    {
        $slides = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $name, $matches)) {
                $slides[(int) $matches[1]] = $name;
            }
        }

        ksort($slides);

        $segments = [];
        $lines = [];

        foreach ($slides as $number => $name) {
            $xml = $zip->getFromName($name);

            if ($xml === false) {
                continue;
            }

            $xpath = $this->xpathFor($xml, ['a' => self::NS_DRAWING]);
            $parts = [];

            // Each <a:p> is a paragraph within a shape; joining runs per
            // paragraph keeps bullet points as separate lines.
            foreach ($xpath->query('//a:p') ?: [] as $paragraph) {
                $text = trim(preg_replace('/\s+/u', ' ', (string) $paragraph->textContent) ?? '');

                if ($text !== '') {
                    $parts[] = $text;
                }
            }

            if (! $parts) {
                continue;
            }

            $heading = $parts[0];
            $block = implode("\n", $parts);
            $lines[] = $block;
            $segments[] = ['text' => $block, 'page' => $number, 'heading' => $heading, 'type' => 'slide'];
        }

        return new ExtractedContent(
            text: ContentNormaliser::normalise(implode("\n\n", $lines)),
            segments: $segments,
            pageCount: max(1, count($slides)),
            detectedTitle: $segments[0]['heading'] ?? null,
        );
    }

    /** @return array<int, string> */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $xpath = $this->xpathFor($xml, ['s' => self::NS_SHEET]);
        $strings = [];

        foreach ($xpath->query('//s:si') ?: [] as $node) {
            $strings[] = trim(preg_replace('/\s+/u', ' ', (string) $node->textContent) ?? '');
        }

        return $strings;
    }

    /** @return array<int, string> */
    private function readSheetNames(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/workbook.xml');

        if ($xml === false) {
            return [];
        }

        $xpath = $this->xpathFor($xml, ['s' => self::NS_SHEET]);
        $names = [];

        foreach ($xpath->query('//s:sheets/s:sheet/@name') ?: [] as $attribute) {
            $names[] = (string) $attribute->nodeValue;
        }

        return $names;
    }

    /** @param  array<string, string>  $namespaces */
    private function xpathFor(string $xml, array $namespaces): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            // No LIBXML_NOENT: entities stay unexpanded, closing XXE on a file
            // that arrived from outside.
            $document->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $xpath = new DOMXPath($document);

        foreach ($namespaces as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return $xpath;
    }
}
