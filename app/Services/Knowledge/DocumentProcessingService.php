<?php

namespace App\Services\Knowledge;

use App\Contracts\Knowledge\OcrProviderInterface;
use App\Enums\Knowledge\ChunkingStrategy;
use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Events\Knowledge\KnowledgeProcessingCompleted;
use App\Events\Knowledge\KnowledgeProcessingFailed;
use App\Exceptions\Knowledge\ExtractionException;
use App\Exceptions\Knowledge\KnowledgeException;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeUsageRecord;
use App\Services\Knowledge\Data\ExtractedContent;
use App\Services\Knowledge\Extraction\ExtractorRegistry;
use App\Services\Knowledge\Support\ContentNormaliser;
use App\Services\Knowledge\Support\LanguageDetector;
use App\Services\Knowledge\Support\TokenEstimator;
use Throwable;

/**
 * The document processing pipeline.
 *
 *   VALIDATING → EXTRACTING → NORMALIZING → DETECTING LANGUAGE
 *   → DEDUPLICATING → CHUNKING → (embedding, handled asynchronously) → READY
 *
 * Design points that matter:
 *
 *  - Every stage transition goes through ProcessingStateMachine, which refuses
 *    illegal moves and loses races safely, so two workers on the same document
 *    cannot interleave into a corrupt state.
 *  - Re-running the whole pipeline on unchanged content is cheap: the content
 *    hash short-circuits before chunking, and unchanged chunks keep their
 *    vectors. That is what makes retries and re-crawls affordable.
 *  - Embedding is NOT done here. It is dispatched separately so that a rate
 *    limit from the provider retries only the embedding step rather than
 *    re-extracting a 300-page PDF from scratch.
 */
class DocumentProcessingService
{
    public function __construct(
        private readonly ExtractorRegistry $extractors,
        private readonly ChunkingService $chunker,
        private readonly KnowledgeIndexService $index,
        private readonly ProcessingStateMachine $states,
        private readonly ProcessingLogger $logger,
        private readonly KnowledgeStorage $storage,
        private readonly KnowledgeStatisticsService $statistics,
        private readonly KnowledgeVersionService $versions,
        private readonly OcrProviderInterface $ocr,
    ) {}

    /**
     * Runs the pipeline for one document.
     *
     * @return array{status: string, chunks: int, reused: int}
     */
    public function process(KnowledgeDocument $document, ?KnowledgeProcessingJob $job = null, bool $force = false): array
    {
        $logger = $this->logger->forJob($job);

        // Losing this transition means another worker already owns the document
        // — the correct response is to stand down, not to compete.
        if (! $this->states->begin($document)) {
            return ['status' => 'skipped_not_owned', 'chunks' => 0, 'reused' => 0];
        }

        if ($this->cancelled($document, $job)) {
            return ['status' => 'cancelled', 'chunks' => 0, 'reused' => 0];
        }

        $temporaryPath = null;

        try {
            $base = $document->knowledgeBase;
            $source = $document->source;

            // --- EXTRACTING -------------------------------------------------
            $this->states->transition($document, DocumentStatus::Extracting, ProcessingStage::Extracting);
            $extractedAt = hrtime(true);

            $content = $document->kind === KnowledgeDocument::KIND_FILE
                ? $this->extractStoredFile($document, $temporaryPath)
                : $this->contentFromStoredText($document);

            $content = $this->applyOcrIfNeeded($document, $content, $temporaryPath, $logger);

            $logger->stage($document, ProcessingStage::Extracting, 'Extracted document text', [
                'characters' => $content->characterCount(),
                'pages' => $content->pageCount,
                'ocr' => $content->ocrApplied,
            ], (int) ((hrtime(true) - $extractedAt) / 1_000_000));

            if ($this->cancelled($document, $job)) {
                return ['status' => 'cancelled', 'chunks' => 0, 'reused' => 0];
            }

            // --- NORMALIZING --------------------------------------------------
            $this->states->transition($document, DocumentStatus::Processing, ProcessingStage::Normalizing);
            $text = ContentNormaliser::normalise($content->text);

            if ($text === '') {
                throw ExtractionException::empty($document->original_filename ?? $document->title);
            }

            $logger->stage($document, ProcessingStage::Normalizing, 'Normalised document text', [
                'characters' => mb_strlen($text),
            ]);

            // --- DETECTING LANGUAGE -------------------------------------------
            $this->states->transition($document, DocumentStatus::Processing, ProcessingStage::DetectingLanguage);
            $language = $document->language ?: LanguageDetector::detect($text, $base->default_language);
            $logger->stage($document, ProcessingStage::DetectingLanguage, 'Detected document language', [
                'language' => $language,
            ]);

            // --- DEDUPLICATING ------------------------------------------------
            $this->states->transition($document, DocumentStatus::Processing, ProcessingStage::Deduplicating);

            if ($this->cancelled($document, $job)) {
                return ['status' => 'cancelled', 'chunks' => 0, 'reused' => 0];
            }

            $contentHash = ContentNormaliser::hash($text);
            $unchanged = $document->content_hash === $contentHash;

            if ($unchanged && ! $force && $document->chunk_count > 0) {
                // Nothing about the content moved. Skipping here is what makes a
                // weekly re-crawl of a 5,000-page site cost almost nothing.
                $logger->stage($document, ProcessingStage::Deduplicating, 'Content unchanged — skipped re-chunking', [
                    'chunks' => $document->chunk_count,
                ]);

                $this->states->transition($document, DocumentStatus::Chunking, ProcessingStage::Chunking);
                $this->states->transition($document, DocumentStatus::Embedding, ProcessingStage::Embedding);
                $this->states->transition($document, DocumentStatus::Indexing, ProcessingStage::Indexing);
                $this->states->complete($document, $document->chunk_count, $document->chunk_count);

                return ['status' => 'unchanged', 'chunks' => $document->chunk_count, 'reused' => $document->chunk_count];
            }

            // A content change is a new version, recorded before the document
            // row is overwritten so the previous text remains restorable.
            if ($document->content_hash !== null && ! $unchanged) {
                $this->versions->snapshot($document, 'Content updated during processing');
            }

            $document->forceFill([
                'extracted_text' => $text,
                'content_hash' => $contentHash,
                'structure' => $this->summariseStructure($content),
                'language' => $language,
                'character_count' => mb_strlen($text),
                'word_count' => $content->wordCount(),
                'page_count' => max(1, $content->pageCount),
                'token_estimate' => TokenEstimator::estimate($text),
                'ocr_applied' => $content->ocrApplied,
                'has_tables' => $content->hasTables,
                'title' => $document->title ?: ($content->detectedTitle ?? 'Untitled document'),
            ])->save();

            if ($this->cancelled($document, $job)) {
                return ['status' => 'cancelled', 'chunks' => 0, 'reused' => 0];
            }

            // --- CHUNKING -------------------------------------------------------
            $this->states->transition($document, DocumentStatus::Chunking, ProcessingStage::Chunking);
            $chunkedAt = hrtime(true);

            $strategy = $this->strategyFor($document, $base->chunking_strategy);

            $candidates = $this->chunker->chunk(
                new ExtractedContent($text, $content->segments, $content->metadata, $content->pageCount, $content->ocrApplied, $content->hasTables),
                $strategy,
                $base->chunk_size,
                $base->chunk_overlap,
            );

            $result = $this->index->syncDocumentChunks($document, $candidates);

            $logger->stage($document, ProcessingStage::Chunking, 'Split document into chunks', [
                'strategy' => $strategy->value,
                'chunks' => $result['written'],
                'reused_vectors' => $result['reused'],
                'removed' => $result['removed'],
            ], (int) ((hrtime(true) - $chunkedAt) / 1_000_000));

            // --- EMBEDDING / INDEXING -----------------------------------------
            // Chunks needing a new vector are left pending; EmbedKnowledgeBaseJob
            // picks them up. A document with nothing pending is already complete.
            $this->states->transition($document, DocumentStatus::Embedding, ProcessingStage::Embedding);
            $document->forceFill(['chunk_count' => $result['written']])->save();

            $pending = $result['written'] - $result['reused'];

            if ($pending === 0) {
                $this->states->transition($document, DocumentStatus::Indexing, ProcessingStage::Indexing);
                $logger->stage($document, ProcessingStage::Indexing, 'Indexing chunks', ['chunks' => $result['written']]);
                $this->states->complete($document, $result['written'], $result['written']);

                $this->afterProcessing($document);
                KnowledgeProcessingCompleted::dispatch($document->id, $result['written']);

                return ['status' => 'ready', 'chunks' => $result['written'], 'reused' => $result['reused']];
            }

            $logger->stage($document, ProcessingStage::Embedding, 'Queued for embedding', [
                'pending' => $pending,
                'reused' => $result['reused'],
            ]);

            $this->afterProcessing($document);

            return ['status' => 'awaiting_embedding', 'chunks' => $result['written'], 'reused' => $result['reused']];
        } catch (Throwable $e) {
            $stage = $document->current_stage ?? ProcessingStage::Extracting;
            $failure = $logger->failure($stage, $e, $document, null, $document->retry_count + 1);

            $this->states->fail(
                $document,
                $stage,
                $failure->category,
                $failure->message,
            );

            $this->afterProcessing($document);
            KnowledgeProcessingFailed::dispatch($document->id, $failure->uuid);

            throw $e;
        } finally {
            if ($temporaryPath && file_exists($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    /**
     * Cooperative cancellation checkpoint. Checked between expensive stages
     * (never mid-write) so an operator's cancel request is honoured promptly
     * without tearing a transaction in half.
     */
    private function cancelled(KnowledgeDocument $document, ?KnowledgeProcessingJob $job): bool
    {
        if (! $job?->isCancelled()) {
            return false;
        }

        $this->states->cancel($document);
        $this->afterProcessing($document);

        return true;
    }

    private function extractStoredFile(KnowledgeDocument $document, ?string &$temporaryPath): ExtractedContent
    {
        if (! $document->storage_path) {
            throw ExtractionException::unreadable($document->original_filename ?? $document->title);
        }

        $temporaryPath = $this->storage->pullToTemporaryFile($document);

        return $this->extractors->extract(
            $temporaryPath,
            $document->original_filename ?? $document->title,
            $document->mime_type ?? 'application/octet-stream',
        );
    }

    /**
     * Manual text and crawled page bodies already hold their text on the row —
     * there is nothing to extract, but they still need the rest of the pipeline
     * (normalise, detect language, chunk, embed).
     */
    private function contentFromStoredText(KnowledgeDocument $document): ExtractedContent
    {
        $text = (string) KnowledgeDocument::query()
            ->whereKey($document->id)
            ->value('extracted_text');

        if (trim($text) === '') {
            throw ExtractionException::empty($document->title);
        }

        return new ExtractedContent(
            text: $text,
            segments: $document->structure['segments'] ?? [],
            pageCount: 1,
        );
    }

    /**
     * Attempts OCR when extraction produced too little text for the page count.
     *
     * A failed OCR attempt is a warning, not an error: the pipeline continues
     * with whatever text was recovered, and only fails later if that is empty.
     * The alternative — failing the document outright because an optional
     * enhancement was unavailable — would reject scanned PDFs that still had
     * usable embedded text.
     */
    private function applyOcrIfNeeded(
        KnowledgeDocument $document,
        ExtractedContent $content,
        ?string $temporaryPath,
        ProcessingLogger $logger,
    ): ExtractedContent {
        if (! $content->looksLikeScannedDocument() && ! $content->isEmpty()) {
            return $content;
        }

        if (! config('knowledge.extraction.ocr.enabled') || ! $this->ocr->isAvailable() || ! $temporaryPath) {
            if ($content->isEmpty()) {
                throw ExtractionException::empty($document->original_filename ?? $document->title);
            }

            $logger->warn($document, ProcessingStage::Extracting, 'Document looks scanned but OCR is not available', [
                'characters' => $content->characterCount(),
                'pages' => $content->pageCount,
            ]);

            return $content;
        }

        try {
            $maxPages = min($content->pageCount ?: 1, (int) config('knowledge.extraction.ocr.max_pages'));
            $recognised = $this->ocr->recogniseDocument($temporaryPath, $maxPages);

            KnowledgeUsageRecord::accrue(
                $document->knowledge_base_id,
                $document->knowledge_source_id,
                $this->ocr->name(),
                KnowledgeUsageRecord::OPERATION_OCR,
                $maxPages,
                $this->ocr->estimateCost($maxPages),
            );

            $logger->stage($document, ProcessingStage::Extracting, 'Recovered text with OCR', [
                'pages' => $maxPages,
                'characters' => mb_strlen($recognised),
            ]);

            return $content->withText($recognised, ocrApplied: true);
        } catch (KnowledgeException $e) {
            if ($content->isEmpty()) {
                throw $e;
            }

            $logger->warn($document, ProcessingStage::Extracting, 'OCR failed; continuing with extracted text');

            return $content;
        }
    }

    /**
     * FAQ and code content override the base's default strategy: chunking a
     * question away from its answer, or a function mid-body, is wrong
     * regardless of what the base is configured to do generally.
     */
    private function strategyFor(KnowledgeDocument $document, ChunkingStrategy $default): ChunkingStrategy
    {
        return match (true) {
            in_array($document->extension, ['md', 'markdown'], true) => ChunkingStrategy::Markdown,
            in_array($document->extension, ['csv', 'json', 'xml'], true) => ChunkingStrategy::Paragraph,
            default => $default,
        };
    }

    /**
     * Keeps a compact outline of document structure for the preview screen —
     * the segment texts themselves are not duplicated onto the row, since they
     * already live in extracted_text and in the chunks.
     *
     * @return array<string, mixed>
     */
    private function summariseStructure(ExtractedContent $content): array
    {
        $headings = [];
        $pages = [];

        foreach ($content->segments as $segment) {
            if (! empty($segment['heading'])) {
                $headings[] = $segment['heading'];
            }

            if (! empty($segment['page'])) {
                $pages[] = (int) $segment['page'];
            }
        }

        return array_filter([
            'headings' => array_values(array_slice(array_unique($headings), 0, 200)),
            'pages' => $pages ? ['first' => min($pages), 'last' => max($pages)] : null,
            'segment_count' => count($content->segments),
            'has_tables' => $content->hasTables,
        ], fn ($v) => $v !== null && $v !== []);
    }

    private function afterProcessing(KnowledgeDocument $document): void
    {
        $document->source?->refresh();

        if ($document->source) {
            $this->statistics->refreshForSource($document->source);
        }

        if ($document->knowledgeBase) {
            $this->statistics->refreshForBase($document->knowledgeBase);
        }
    }
}
