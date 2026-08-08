<?php

namespace App\Services\Knowledge\Extraction;

use App\Contracts\Knowledge\OcrProviderInterface;
use App\Enums\Knowledge\FailureCategory;
use App\Exceptions\Knowledge\ExtractionException;

/**
 * The default OCR provider: none.
 *
 * OCR needs either a binary (Tesseract) or a paid API, neither of which can be
 * assumed present. Rather than pretend, `isAvailable()` returns false and the
 * pipeline skips the OCR stage entirely, reporting "this looks like a scan and
 * OCR is not configured" instead of a mysterious empty extraction. Registering
 * a real OcrProviderInterface in the container turns the stage on with no other
 * change.
 */
class NullOcrProvider implements OcrProviderInterface
{
    public function isAvailable(): bool
    {
        return false;
    }

    public function recogniseDocument(string $path, int $maxPages): string
    {
        throw new ExtractionException(
            'OCR was requested but no OCR provider is configured',
            FailureCategory::ExtractionError,
            'This document appears to be scanned, and OCR is not set up for this installation. '
            .'Upload a text-based copy, or ask your administrator to configure an OCR provider.',
        );
    }

    public function name(): string
    {
        return 'null';
    }

    public function estimateCost(int $pages): float
    {
        return 0.0;
    }
}
