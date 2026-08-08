<?php

namespace App\Contracts\Knowledge;

use App\Services\Knowledge\Data\ExtractedContent;

interface DocumentExtractorInterface
{
    /**
     * Whether this extractor handles the given file. Matching is on the sniffed
     * MIME type first and extension only as a tiebreak — an attacker controls
     * the filename, not the file's magic bytes.
     */
    public function supports(string $mimeType, string $extension): bool;

    /**
     * @param  string  $path  Absolute path to a local copy of the file.
     *
     * @throws \App\Exceptions\Knowledge\ExtractionException
     */
    public function extract(string $path, string $originalFilename): ExtractedContent;

    /** Higher runs first, letting a specific extractor pre-empt a generic one. */
    public function priority(): int;
}
