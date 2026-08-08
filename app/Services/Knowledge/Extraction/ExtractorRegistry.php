<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\DocumentExtractorInterface;
use App\Exceptions\Knowledge\ExtractionException;
use App\Services\Knowledge\Data\ExtractedContent;

/**
 * Chooses and runs the right extractor for a file.
 *
 * Selection keys on the *sniffed* MIME type, with the extension as a secondary
 * signal only. The filename is attacker-controlled; the magic bytes are not.
 */
class ExtractorRegistry
{
    /** @var array<int, DocumentExtractorInterface> */
    private array $extractors;

    /** @param  array<int, DocumentExtractorInterface>  $extractors */
    public function __construct(array $extractors)
    {
        usort($extractors, fn ($a, $b) => $b->priority() <=> $a->priority());

        $this->extractors = $extractors;
    }

    public function extract(string $path, string $originalFilename, string $mimeType): ExtractedContent
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $extractor = $this->resolve($mimeType, $extension);

        if (! $extractor) {
            throw ExtractionException::unsupported($mimeType, $originalFilename);
        }

        $content = $extractor->extract($path, $originalFilename);

        if ($content->isEmpty()) {
            throw ExtractionException::empty($originalFilename);
        }

        return $content;
    }

    public function resolve(string $mimeType, string $extension): ?DocumentExtractorInterface
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mimeType, $extension)) {
                return $extractor;
            }
        }

        return null;
    }

    public function supports(string $mimeType, string $extension): bool
    {
        return $this->resolve($mimeType, $extension) !== null;
    }
}
