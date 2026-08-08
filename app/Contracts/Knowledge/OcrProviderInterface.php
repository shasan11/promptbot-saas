<?php

namespace App\Contracts\Knowledge;

interface OcrProviderInterface
{
    public function isAvailable(): bool;

    /**
     * Runs OCR over a document, returning recovered plain text.
     *
     * @param  int  $maxPages  Hard cap — OCR is billed per page and a
     *                         thousand-page scan can cost more than the
     *                         customer's subscription.
     *
     * @throws \App\Exceptions\Knowledge\ExtractionException
     */
    public function recogniseDocument(string $path, int $maxPages): string;

    public function name(): string;

    public function estimateCost(int $pages): float;
}
