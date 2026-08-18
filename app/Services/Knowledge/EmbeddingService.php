<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeUsageRecord;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Turns pending chunks into vectors.
 *
 * Runs in batches sized to the provider's limit, and treats a batch as the unit
 * of failure: if a call fails, that batch's chunks stay pending and the job
 * retries them, while already-embedded batches keep their vectors. That is what
 * makes a partially-embedded document recoverable rather than needing a full
 * restart when the provider rate-limits us halfway through.
 */
class EmbeddingService
{
    public function __construct(
        private readonly EmbeddingProviderFactory $providers,
        private readonly ProcessingLogger $logger,
    ) {}

    /**
     * Embeds up to `$limit` pending chunks in the base.
     *
     * @return array{embedded: int, failed: int, tokens: int, cost: float}
     */
    public function embedPending(KnowledgeBase $base, int $limit = 500): array
    {
        $provider = $this->providers->forKnowledgeBase($base);
        $batchSize = min($provider->maxBatchSize(), (int) config('knowledge.embeddings.batch_size'));

        $embedded = 0;
        $failed = 0;
        $tokens = 0;
        $cost = 0.0;
        $processed = 0;

        while ($processed < $limit) {
            $chunks = KnowledgeChunk::query()
                ->where('knowledge_base_id', $base->id)
                ->where('embedding_status', KnowledgeChunk::EMBEDDING_PENDING)
                ->orderBy('id')
                ->limit(min($batchSize, $limit - $processed))
                ->get(['id', 'content', 'knowledge_document_id']);

            if ($chunks->isEmpty()) {
                break;
            }

            $processed += $chunks->count();
            $document = $this->soleDocumentFor($chunks);

            try {
                $result = $provider->embedBatch($chunks->pluck('content')->all());

                if ($result->count() !== $chunks->count()) {
                    throw EmbeddingException::misalignedBatch($provider->name(), $chunks->count(), $result->count());
                }

                if ($result->dimensions !== $base->embedding_dimensions) {
                    throw EmbeddingException::dimensionMismatch($base->embedding_dimensions, $result->dimensions);
                }

                DB::transaction(function () use ($chunks, $result, $base): void {
                    foreach ($chunks as $offset => $chunk) {
                        // Written per row rather than as a bulk upsert: the
                        // payload is binary and each row's vector differs, so
                        // there is nothing to share between statements anyway.
                        KnowledgeChunk::query()->whereKey($chunk->id)->update([
                            'embedding' => KnowledgeChunk::packVector($result->vectors[$offset]),
                            'embedding_provider' => $result->provider,
                            'embedding_model' => $result->model,
                            'embedding_dimensions' => $result->dimensions,
                            'embedding_version' => $base->embedding_version,
                            'embedding_status' => KnowledgeChunk::EMBEDDING_READY,
                            'embedded_at' => now(),
                            'is_retrievable' => true,
                            'updated_at' => now(),
                        ]);
                    }
                });

                $embedded += $chunks->count();
                $tokens += $result->tokensUsed;
                $cost += $result->estimatedCost;

                $this->logger->stage(
                    $document,
                    ProcessingStage::Embedding,
                    'Embedded chunk batch',
                    ['chunks' => $chunks->count(), 'provider' => $result->provider, 'tokens' => $result->tokensUsed],
                    $result->latencyMs,
                    knowledgeBaseId: $base->id,
                );
            } catch (EmbeddingException $e) {
                // Transient problems (rate limit, timeout) leave the chunks
                // pending so the retrying job picks them up unchanged. Permanent
                // ones mark them failed, because retrying forever would just
                // hold the queue open.
                if (! $e->isRetryable()) {
                    $this->markBatchFailed($chunks, $e, $document, $base);

                    $failed += $chunks->count();

                    continue;
                }

                $this->logger->failure(ProcessingStage::Embedding, $e, $document, knowledgeBaseId: $base->id);

                throw $e;
            } catch (Throwable $e) {
                // Anything the provider driver didn't wrap as an EmbeddingException
                // — a config error, a malformed response causing a TypeError, an
                // unexpected network exception. Previously this propagated out of
                // the loop with nothing ever recorded, so a misbehaving provider
                // left chunks (and their documents) stuck at "pending"/"embedding"
                // with no trace of why in the Knowledge module's own logs. Wrap it
                // so it carries a category, record it, and let it propagate: we
                // cannot safely assume an unrecognised failure is permanent, so
                // the job-level retry/backoff (and, if that's exhausted, the job's
                // own failed() callback) is what ultimately resolves it.
                $wrapped = EmbeddingException::providerFailed($provider->name(), $e->getMessage(), $e);

                $this->logger->failure(ProcessingStage::Embedding, $wrapped, $document, knowledgeBaseId: $base->id);

                throw $wrapped;
            }
        }

        if ($tokens > 0) {
            KnowledgeUsageRecord::accrue(
                knowledgeBaseId: $base->id,
                knowledgeSourceId: null,
                provider: $provider->name(),
                operation: KnowledgeUsageRecord::OPERATION_EMBEDDING,
                units: $tokens,
                cost: $cost,
                requests: (int) ceil($embedded / max(1, $batchSize)),
            );
        }

        return ['embedded' => $embedded, 'failed' => $failed, 'tokens' => $tokens, 'cost' => $cost];
    }

    /**
     * Embeds a search query with the same provider that produced the base's
     * chunk vectors. Using a different one silently yields nonsense similarity.
     *
     * @return array<int, float>
     */
    public function embedQuery(KnowledgeBase $base, string $query): array
    {
        $result = $this->providers->forKnowledgeBase($base)->embed($query);

        return $result->vectors[0] ?? [];
    }

    public function pendingCount(KnowledgeBase $base): int
    {
        return KnowledgeChunk::query()
            ->where('knowledge_base_id', $base->id)
            ->where('embedding_status', KnowledgeChunk::EMBEDDING_PENDING)
            ->count();
    }

    /**
     * Marks every chunk in a permanently-failed batch as failed and records
     * one failure per distinct document the batch touched, so each affected
     * document's own failure trail (and the base's Failed Sources page) shows
     * the real reason — not a single anonymous entry with no owner.
     */
    /** @param  Collection<int, KnowledgeChunk>  $chunks */
    private function markBatchFailed(Collection $chunks, EmbeddingException $e, ?KnowledgeDocument $soleDocument, KnowledgeBase $base): void
    {
        KnowledgeChunk::query()
            ->whereIn('id', $chunks->pluck('id'))
            ->update(['embedding_status' => KnowledgeChunk::EMBEDDING_FAILED, 'is_retrievable' => false]);

        if ($soleDocument) {
            $this->logger->failure(ProcessingStage::Embedding, $e, $soleDocument, knowledgeBaseId: $base->id);

            return;
        }

        $documentIds = $chunks->pluck('knowledge_document_id')->filter()->unique();

        if ($documentIds->isEmpty()) {
            $this->logger->failure(ProcessingStage::Embedding, $e, knowledgeBaseId: $base->id);

            return;
        }

        foreach (KnowledgeDocument::query()->whereIn('id', $documentIds)->get(['id', 'knowledge_base_id', 'knowledge_source_id']) as $document) {
            $this->logger->failure(ProcessingStage::Embedding, $e, $document, knowledgeBaseId: $base->id);
        }
    }

    /**
     * Resolves the batch's document when every chunk in it belongs to the same
     * one — the common case.
     *
     * @param  Collection<int, KnowledgeChunk>  $chunks
     */
    private function soleDocumentFor(Collection $chunks): ?KnowledgeDocument
    {
        $documentIds = $chunks->pluck('knowledge_document_id')->filter()->unique();

        if ($documentIds->count() !== 1) {
            return null;
        }

        return KnowledgeDocument::query()->find($documentIds->first(), ['id', 'knowledge_base_id', 'knowledge_source_id']);
    }
}
