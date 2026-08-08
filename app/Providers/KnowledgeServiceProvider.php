<?php

namespace App\Providers;

use App\Contracts\Knowledge\OcrProviderInterface;
use App\Contracts\Knowledge\ReRankerInterface;
use App\Contracts\Knowledge\VectorStoreInterface;
use App\Events\Knowledge\KnowledgeBaseAccessChanged;
use App\Listeners\Knowledge\FlushKnowledgePermissionCache;
use App\Services\Knowledge\Extraction\ExtractorRegistry;
use App\Services\Knowledge\Extraction\HtmlExtractor;
use App\Services\Knowledge\Extraction\LegacyOfficeExtractor;
use App\Services\Knowledge\Extraction\NullOcrProvider;
use App\Services\Knowledge\Extraction\OfficeOpenXmlExtractor;
use App\Services\Knowledge\Extraction\PdfExtractor;
use App\Services\Knowledge\Extraction\PlainTextExtractor;
use App\Services\Knowledge\Retrieval\DatabaseVectorStore;
use App\Services\Knowledge\Retrieval\HeuristicReRanker;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Wires the Knowledge Base module's swappable pieces.
 *
 * Everything resolved through an interface here is a genuine extension point —
 * a different vector database, a real cross-encoder re-ranker, an OCR provider,
 * an extractor for a format we do not yet read. Adding one means binding it
 * here; no pipeline code changes.
 */
class KnowledgeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(VectorStoreInterface::class, function () {
            $driver = (string) config('knowledge.vector_store.driver');

            return match ($driver) {
                'database' => new DatabaseVectorStore,
                default => throw new InvalidArgumentException("Unknown knowledge vector store driver [{$driver}]."),
            };
        });

        $this->app->singleton(ReRankerInterface::class, function () {
            return match ((string) config('knowledge.retrieval.reranking.driver')) {
                'heuristic' => new HeuristicReRanker,
                default => new HeuristicReRanker,
            };
        });

        $this->app->singleton(OcrProviderInterface::class, function () {
            return match ((string) config('knowledge.extraction.ocr.provider')) {
                // No OCR provider ships with the module — see NullOcrProvider
                // for why that is a deliberate choice rather than an omission.
                default => new NullOcrProvider,
            };
        });

        $this->app->singleton(ExtractorRegistry::class, function ($app) {
            // Order here is irrelevant; the registry sorts by priority(). A
            // custom extractor with a higher priority pre-empts a built-in one
            // for the same MIME type.
            return new ExtractorRegistry([
                $app->make(PdfExtractor::class),
                $app->make(OfficeOpenXmlExtractor::class),
                $app->make(HtmlExtractor::class),
                $app->make(PlainTextExtractor::class),
                $app->make(LegacyOfficeExtractor::class),
            ]);
        });
    }

    public function boot(): void
    {
        // Registered explicitly rather than relying on discovery: a missed
        // permission-cache flush is a disclosure bug, so the wiring should be
        // visible in one place.
        Event::listen(KnowledgeBaseAccessChanged::class, FlushKnowledgePermissionCache::class);
    }
}
