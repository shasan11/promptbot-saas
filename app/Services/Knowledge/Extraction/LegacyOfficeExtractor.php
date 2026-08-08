<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Enums\Knowledge\FailureCategory;
use App\Exceptions\Knowledge\ExtractionException;
use App\Services\Knowledge\Data\ExtractedContent;

/**
 * Legacy binary Office formats (.doc, .xls, .ppt).
 *
 * These are accepted at upload — users have them and rejecting the upload
 * outright reads as a bug — but the OLE compound-document format is not
 * something to hand-roll a parser for. This extractor exists so the failure is
 * a clear, actionable message on the Failed Sources page rather than a generic
 * "no extractor found", and so the format has one obvious place to gain real
 * support later.
 */
class LegacyOfficeExtractor implements DocumentExtractorInterface
{
    public function supports(string $mimeType, string $extension): bool
    {
        return in_array($mimeType, [
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
        ], true) || in_array(strtolower($extension), ['doc', 'xls', 'ppt'], true);
    }

    public function extract(string $path, string $originalFilename): ExtractedContent
    {
        $extension = strtoupper(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $modern = match (strtolower($extension)) {
            'doc' => 'DOCX', 'xls' => 'XLSX', 'ppt' => 'PPTX', default => 'a modern Office format',
        };

        throw new ExtractionException(
            "Legacy binary Office format [{$extension}] is not supported: {$originalFilename}",
            FailureCategory::InvalidFile,
            "PromptBot cannot read the older {$extension} format. Open the file in Office and re-save it as {$modern}, then upload it again.",
        );
    }

    public function priority(): int
    {
        // Lowest of the real extractors: anything that can genuinely parse
        // these should take precedence the moment it is registered.
        return 5;
    }
}
