<?php

namespace Tests\Feature\Tenant\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\RetrievalMode;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\Data\RetrievalHit;
use App\Services\Knowledge\Data\RetrievalQuery;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Services\Knowledge\KnowledgeRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Support\Knowledge\FakeEmbeddingProviderFactory;
use Tests\Support\Knowledge\TopicEmbeddingProvider;
use Tests\TestCase;

/**
 * Guards the retrieval *admission* decision — whether a candidate is good
 * enough to be shown to the model at all, as distinct from where it ranks.
 *
 * The bug this replaces: admission was judged on the RRF-fused score, and RRF
 * rescales onto the best hit of the query's own result set. The top fused
 * score was therefore near-maximal by construction — for a perfect match and
 * for a question the corpus knows nothing about alike — so the similarity
 * threshold could not reject anything. The keyword score had the same shape of
 * defect independently, normalising against the query's own best hit.
 *
 * Everything here runs against a real tenant database and the real pipeline;
 * only the embedding provider is a double, so that "relevant" and "off-topic"
 * are exact rather than a property of whichever model is configured.
 */
class KnowledgeRetrievalAdmissionTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private const REFUND_TEXT = 'Refund policy. Customers may request a refund on any order within thirty days of the purchase date. Refunded orders are credited to the original payment method.';

    private const SHIPPING_TEXT = 'Shipping and delivery. Every parcel ships with courier tracking, and delivery to mainland addresses takes two to four working days.';

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    /**
     * The headline property: a question the corpus can answer is admitted, and
     * a question about something else entirely is not — using one corpus, one
     * threshold and two queries, which is exactly the comparison the old fused
     * score could not make.
     */
    public function test_admission_separates_a_relevant_question_from_an_off_topic_one(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        $base = $this->seedCorpus($admin->id);
        $threshold = (float) config('knowledge.retrieval.default_similarity_threshold');

        $relevant = $this->retrieve($base, 'how do I get a refund for my order', $threshold);

        $this->assertNotEmpty($relevant->hits, 'A question the corpus plainly answers was rejected.');
        $this->assertGreaterThanOrEqual($threshold, $relevant->hits[0]->semanticScore);
        $this->assertStringContainsString('refund', mb_strtolower($relevant->hits[0]->chunk->content));

        // "What is the best sourdough bread recipe?" is the query that used to
        // score 1.000 against a product manual.
        $offTopic = $this->retrieve($base, 'what is the best sourdough bread recipe to bake at home', $threshold);

        $this->assertEmpty($offTopic->hits, 'An off-topic question was admitted into the model context.');
        $this->assertSame('', $offTopic->context);

        // It must be rejected *by the threshold*, not merely absent — an empty
        // candidate set would pass the assertion above for the wrong reason.
        $this->assertNotEmpty($offTopic->discarded);
        $this->assertContains(
            'below_similarity_threshold',
            array_map(fn (RetrievalHit $hit) => $hit->exclusionReason, $offTopic->discarded),
        );

        tenancy()->end();
    }

    /**
     * Admission is judged on raw cosine similarity, so the *same passage and
     * question* must produce the same admission score no matter what else the
     * query returned alongside it. Self-normalisation is precisely the failure
     * of that property.
     */
    public function test_the_admission_score_does_not_depend_on_the_rest_of_the_result_set(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        $base = $this->seedCorpus($admin->id);

        $narrow = $this->retrieve($base, 'refund policy for an order', 0.0, topK: 1);
        $wide = $this->retrieve($base, 'refund policy for an order', 0.0, topK: 10);

        $this->assertNotEmpty($narrow->hits);
        $this->assertNotEmpty($wide->hits);
        $this->assertSame($narrow->hits[0]->chunk->id, $wide->hits[0]->chunk->id);
        $this->assertEqualsWithDelta($narrow->hits[0]->semanticScore, $wide->hits[0]->semanticScore, 0.0001);

        // And the score is a real cosine, not a rescaled 1.0.
        $this->assertLessThanOrEqual(1.0, $wide->hits[0]->semanticScore);

        tenancy()->end();
    }

    /**
     * The keyword half had the same defect independently: relevance was
     * divided by the best hit in the query's own result set, so the top
     * keyword match scored exactly 1.0 whether its absolute relevance was 40
     * or 0.0001. The saturating transform that replaced it is bounded below 1
     * by construction.
     */
    public function test_the_keyword_score_is_absolute_rather_than_normalised_to_the_best_hit(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        $base = $this->seedCorpus($admin->id);

        // Threshold 0 so the hits come back and can be inspected; this test is
        // about the shape of the score, not about admission.
        $outcome = $this->retrieve($base, 'refund policy', 0.0, mode: RetrievalMode::Keyword);

        $this->assertNotEmpty($outcome->hits, 'Full-text found nothing — the corpus or the index is not what this test assumes.');

        foreach ($outcome->hits as $hit) {
            $this->assertGreaterThan(0.0, $hit->keywordScore);
            $this->assertLessThan(1.0, $hit->keywordScore, 'A keyword score of exactly 1.0 is the self-normalisation bug.');
        }

        tenancy()->end();
    }

    private function retrieve(
        KnowledgeBase $base,
        string $question,
        float $threshold,
        int $topK = 5,
        RetrievalMode $mode = RetrievalMode::Hybrid,
    ) {
        return app(KnowledgeRetrievalService::class)->retrieve(new RetrievalQuery(
            query: $question,
            knowledgeBaseIds: [$base->id],
            mode: $mode,
            topK: $topK,
            similarityThreshold: $threshold,
            // Re-ranking reorders; it has no say in admission. Off here so the
            // assertions describe the retrieval decision alone.
            rerank: false,
            channel: 'test',
        ), $base);
    }

    /**
     * Builds a two-document corpus through the real processing pipeline, so
     * the chunks under test are stored, embedded and marked retrievable
     * exactly as production chunks are.
     */
    private function seedCorpus(int $actorId): KnowledgeBase
    {
        app()->instance(EmbeddingProviderFactory::class, new FakeEmbeddingProviderFactory(new TopicEmbeddingProvider));

        $base = KnowledgeBase::create([
            'name' => 'Retrieval Admission Base',
            'slug' => 'retrieval-admission-'.Str::lower(Str::random(6)),
            'status' => KnowledgeBaseStatus::Active->value,
            'visibility' => KnowledgeVisibility::Workspace->value,
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'embedding_provider' => 'topic-test',
            'embedding_model' => 'topic-test-model',
            'embedding_dimensions' => (new TopicEmbeddingProvider)->dimensions(),
            'embedding_version' => 1,
            'chunking_strategy' => 'paragraph',
            'chunk_size' => 800,
            'chunk_overlap' => 0,
            'retrieval_mode' => 'hybrid',
            'top_k' => 5,
            'candidate_pool' => 20,
            'similarity_threshold' => 0,
            'reranking_enabled' => false,
            'max_context_tokens' => 4000,
            'allow_cross_source_retrieval' => true,
            'prefer_recent_content' => false,
            'require_citations' => true,
            'exclude_expired_content' => true,
            'created_by' => $actorId,
        ]);

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $base->id,
            'source_type' => SourceType::ManualText->value,
            'name' => 'Manual entries',
            'status' => SourceStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        foreach ([self::REFUND_TEXT, self::SHIPPING_TEXT] as $index => $text) {
            $document = KnowledgeDocument::create([
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $source->id,
                'title' => 'Help article '.($index + 1),
                'kind' => KnowledgeDocument::KIND_MANUAL_TEXT,
                'extracted_text' => $text,
                'status' => DocumentStatus::Queued->value,
                'created_by' => $actorId,
            ]);

            $job = KnowledgeProcessingJob::create([
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $source->id,
                'knowledge_document_id' => $document->id,
                'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
                'queue' => config('knowledge.queues.processing'),
                'status' => ProcessingJobStatus::Queued->value,
                'queued_at' => now(),
                'max_attempts' => (int) config('knowledge.processing.max_attempts'),
                'correlation_id' => (string) Str::uuid(),
            ]);

            ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id);

            $this->assertSame(DocumentStatus::Ready, $document->fresh()->status, 'Corpus document failed to process.');
        }

        return $base;
    }
}
