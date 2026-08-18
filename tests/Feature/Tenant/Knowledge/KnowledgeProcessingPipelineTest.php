<?php

namespace Tests\Feature\Tenant\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Enums\Knowledge\ProcessingStage;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Exceptions\Knowledge\EmbeddingException;
use App\Jobs\Knowledge\EmbedKnowledgeBaseJob;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\DocumentProcessingService;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Services\Knowledge\EmbeddingService;
use App\Services\Knowledge\KnowledgeStatisticsService;
use App\Services\Knowledge\ProcessingJobTracker;
use App\Services\Knowledge\ProcessingStateMachine;
use App\Services\Knowledge\StaleProcessingRecoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Support\Knowledge\FakeEmbeddingProviderFactory;
use Tests\Support\Knowledge\PredicateEmbeddingProvider;
use Tests\Support\Knowledge\ThrowingEmbeddingProvider;
use Tests\TestCase;

/**
 * Exercises the knowledge document processing lifecycle end to end: state
 * transitions, retry legality, embedding completion semantics, stale-worker
 * recovery, and cancellation. See the accompanying fix for the bugs these
 * guard against.
 */
class KnowledgeProcessingPipelineTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_normal_document_reaches_ready_with_completed_processing_job(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Customers can request refunds within 30 days of purchase.');
        $job = $this->createProcessingJob($document);

        ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id);

        $document->refresh();
        $job->refresh();

        $this->assertSame(DocumentStatus::Ready, $document->status);
        $this->assertSame(ProcessingJobStatus::Completed, $job->status);
        $this->assertSame(100, $job->progress);
        $this->assertNotNull($job->finished_at);
        $this->assertGreaterThan(0, $document->chunk_count);

        $chunks = KnowledgeChunk::where('owner_key', $document->chunkOwnerKey())->get();
        $this->assertNotEmpty($chunks);
        $this->assertTrue($chunks->every(fn (KnowledgeChunk $c) => $c->embedding_status === KnowledgeChunk::EMBEDDING_READY));
        $this->assertTrue($chunks->every(fn (KnowledgeChunk $c) => $c->is_retrievable));

        tenancy()->end();
    }

    public function test_extraction_permanent_failure_marks_document_and_job_failed(): void
    {
        Storage::fake('local');

        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createStoredFileDocument($admin->id, extension: 'bin', mimeType: 'application/octet-stream');
        Storage::disk('local')->put($document->storage_path, 'not a real document');
        $job = $this->createProcessingJob($document);

        ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id);

        $document->refresh();
        $job->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(ProcessingJobStatus::Failed, $job->status);
        $this->assertNotNull($document->last_error);
        $this->assertDatabaseHas('knowledge_failures', ['knowledge_document_id' => $document->id]);

        tenancy()->end();
    }

    /**
     * Reproduces the original bug directly: a retryable failure used to leave
     * the document in `failed`, and the next attempt's begin() call then threw
     * trying `failed -> validating`. Calls handle() twice with a stubbed
     * attempt counter to simulate Laravel's real retry cycle deterministically.
     */
    public function test_transient_failure_retry_is_legal_and_second_attempt_succeeds(): void
    {
        Storage::fake('local');

        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Escalate urgent billing disputes within one business hour.');
        $job = $this->createProcessingJob($document);

        $processKnowledgeDocumentJob = new ProcessKnowledgeDocumentJob($document->id, $job->id);
        $processKnowledgeDocumentJob->job = new class
        {
            public function attempts(): int
            {
                return 1;
            }
        };

        // Force the pipeline to fail on attempt 1 by making the document
        // extraction throw a retryable error: point it at a stored file with
        // no bytes on disk, which the storage layer reports as a (retryable)
        // storage error.
        $document->forceFill(['kind' => KnowledgeDocument::KIND_FILE, 'storage_path' => 'missing/'.Str::uuid().'.txt', 'storage_disk' => 'local', 'mime_type' => 'text/plain', 'extension' => 'txt'])->save();

        $states = app(ProcessingStateMachine::class);

        try {
            $processKnowledgeDocumentJob->handle(app(DocumentProcessingService::class), $states, app(ProcessingJobTracker::class));
            $this->fail('Expected the first attempt to throw so Laravel would retry it.');
        } catch (\Throwable) {
            // expected — attempt 1 fails
        }

        $document->refresh();
        $job->refresh();

        $this->assertSame(DocumentStatus::Queued, $document->status, 'A retryable failure must leave the document queued for the next attempt, not stuck at failed.');
        $this->assertSame(ProcessingJobStatus::Retrying, $job->status);
        $this->assertNull($job->finished_at, 'A job that will be retried is not finished yet.');

        // Attempt 2: fix the document so processing can actually complete,
        // then run handle() again as Laravel's worker would on retry.
        $document->forceFill([
            'kind' => KnowledgeDocument::KIND_MANUAL_TEXT,
            'extracted_text' => 'Escalate urgent billing disputes within one business hour.',
            'storage_path' => null,
        ])->save();

        $processKnowledgeDocumentJob->job = new class
        {
            public function attempts(): int
            {
                return 2;
            }
        };

        // This must NOT throw "Illegal knowledge document transition failed -> validating".
        $processKnowledgeDocumentJob->handle(app(DocumentProcessingService::class), $states, app(ProcessingJobTracker::class));

        $document->refresh();
        $job->refresh();

        $this->assertSame(DocumentStatus::Ready, $document->status);
        $this->assertSame(ProcessingJobStatus::Completed, $job->status);

        tenancy()->end();
    }

    public function test_partial_embedding_failure_marks_document_partially_ready(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, str_repeat("Paragraph one about refunds.\n\n", 1));

        // Ten chunks, two of which will fail embedding permanently.
        $document->forceFill(['chunk_count' => 0])->save();
        $source = $document->source;
        $chunkIds = [];

        for ($i = 0; $i < 10; $i++) {
            $chunk = KnowledgeChunk::create([
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $source->id,
                'knowledge_document_id' => $document->id,
                'owner_key' => $document->chunkOwnerKey(),
                'chunk_index' => $i,
                'content' => $i < 2 ? 'FAIL-ME chunk '.$i : 'Safe chunk '.$i,
                'content_hash' => hash('sha256', 'chunk-'.$i),
                'token_count' => 5,
                'character_count' => 20,
                'language' => 'en',
                'metadata' => ['document_name' => $document->title],
                'source_type' => SourceType::ManualText->value,
                'embedding_status' => KnowledgeChunk::EMBEDDING_PENDING,
                'is_retrievable' => false,
            ]);
            $chunkIds[] = $chunk->id;
        }

        $document->forceFill(['status' => DocumentStatus::Embedding->value, 'current_stage' => ProcessingStage::Embedding->value, 'chunk_count' => 10])->save();

        app()->instance(EmbeddingProviderFactory::class, new FakeEmbeddingProviderFactory(
            new PredicateEmbeddingProvider($base->embedding_dimensions, fn (string $text) => str_starts_with($text, 'FAIL-ME'))
        ));
        config(['knowledge.embeddings.batch_size' => 1]);

        app(EmbedKnowledgeBaseJob::class, ['knowledgeBaseId' => $base->id])->handle(
            app(EmbeddingService::class),
            app(KnowledgeStatisticsService::class),
            app(ProcessingStateMachine::class),
            app(ProcessingJobTracker::class),
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::PartiallyReady, $document->status);

        $ready = KnowledgeChunk::whereIn('id', $chunkIds)->where('embedding_status', KnowledgeChunk::EMBEDDING_READY)->count();
        $failed = KnowledgeChunk::whereIn('id', $chunkIds)->where('embedding_status', KnowledgeChunk::EMBEDDING_FAILED)->count();

        $this->assertSame(8, $ready);
        $this->assertSame(2, $failed);
        $this->assertSame(8, KnowledgeChunk::whereIn('id', $chunkIds)->where('is_retrievable', true)->count());

        tenancy()->end();
    }

    public function test_all_embeddings_failing_marks_document_failed_not_ready(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content that will never embed.');
        $source = $document->source;

        for ($i = 0; $i < 4; $i++) {
            KnowledgeChunk::create([
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $source->id,
                'knowledge_document_id' => $document->id,
                'owner_key' => $document->chunkOwnerKey(),
                'chunk_index' => $i,
                'content' => 'chunk '.$i,
                'content_hash' => hash('sha256', 'chunk-'.$i),
                'token_count' => 5,
                'character_count' => 10,
                'language' => 'en',
                'metadata' => [],
                'source_type' => SourceType::ManualText->value,
                'embedding_status' => KnowledgeChunk::EMBEDDING_PENDING,
                'is_retrievable' => false,
            ]);
        }

        $document->forceFill(['status' => DocumentStatus::Embedding->value, 'current_stage' => ProcessingStage::Embedding->value, 'chunk_count' => 4])->save();

        app()->instance(EmbeddingProviderFactory::class, new FakeEmbeddingProviderFactory(
            new PredicateEmbeddingProvider($base->embedding_dimensions, fn () => true)
        ));
        config(['knowledge.embeddings.batch_size' => 1]);

        app(EmbedKnowledgeBaseJob::class, ['knowledgeBaseId' => $base->id])->handle(
            app(EmbeddingService::class),
            app(KnowledgeStatisticsService::class),
            app(ProcessingStateMachine::class),
            app(ProcessingJobTracker::class),
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertNotSame(DocumentStatus::Ready, $document->status);
        $this->assertNotSame(DocumentStatus::PartiallyReady, $document->status);

        tenancy()->end();
    }

    public function test_stale_processing_job_is_released_and_document_becomes_retryable(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content stuck behind a dead worker.');
        $document->forceFill(['status' => DocumentStatus::Extracting->value, 'current_stage' => ProcessingStage::Extracting->value])->save();

        $job = $this->createProcessingJob($document);
        $job->forceFill([
            'status' => ProcessingJobStatus::Running->value,
            'started_at' => now()->subMinutes((int) config('knowledge.processing.stale_job_after_minutes') + 30),
        ])->save();

        $released = app(StaleProcessingRecoveryService::class)->releaseStaleJobs();

        $this->assertSame(1, $released);

        $document->refresh();
        $job->refresh();

        $this->assertSame(ProcessingJobStatus::Failed, $job->status);
        $this->assertNotNull($job->finished_at);
        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertNotNull($document->last_error);

        // And it must be legally retryable afterwards — the whole point.
        $states = app(ProcessingStateMachine::class);
        $this->assertTrue($states->requeueForRetry($document));
        $this->assertSame(DocumentStatus::Queued, $document->refresh()->status);

        tenancy()->end();
    }

    public function test_cancelling_a_queued_job_moves_document_out_of_queued(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);
        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content nobody has started processing yet.');
        $job = $this->createProcessingJob($document);
        tenancy()->end();

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/knowledge/processing/{$job->uuid}/cancel")
            ->assertRedirect();

        tenancy()->initialize($tenant);

        $job->refresh();
        $document->refresh();

        $this->assertSame(ProcessingJobStatus::Cancelled, $job->status);
        $this->assertNotNull($job->finished_at);
        $this->assertNotSame(DocumentStatus::Queued, $document->status);
        $this->assertSame(DocumentStatus::Cancelled, $document->status);

        tenancy()->end();
    }

    public function test_cancelling_a_reindex_preserves_previously_ready_chunks(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Working knowledge that already answers queries.');
        $document->forceFill([
            'status' => DocumentStatus::Ready->value,
            'chunk_count' => 3,
            'indexed_at' => now(),
        ])->save();

        $chunk = KnowledgeChunk::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $document->knowledge_source_id,
            'knowledge_document_id' => $document->id,
            'owner_key' => $document->chunkOwnerKey(),
            'chunk_index' => 0,
            'content' => 'Working knowledge content.',
            'content_hash' => hash('sha256', 'working'),
            'token_count' => 5,
            'character_count' => 20,
            'language' => 'en',
            'metadata' => [],
            'source_type' => SourceType::ManualText->value,
            'is_retrievable' => true,
        ]);
        // embedding_status is not mass-assignable (setVector() is the sanctioned
        // writer in production code); set it directly, as existing tests do.
        $chunk->forceFill(['embedding_status' => KnowledgeChunk::EMBEDDING_READY])->save();

        $states = app(ProcessingStateMachine::class);

        // A reindex was started (Ready -> Queued -> Validating -> Extracting)...
        $this->assertTrue($states->transition($document, DocumentStatus::Queued, ProcessingStage::Uploaded));
        $this->assertTrue($states->transition($document, DocumentStatus::Validating, ProcessingStage::Validating));
        $this->assertTrue($states->transition($document, DocumentStatus::Extracting, ProcessingStage::Extracting));

        // ...and then cancelled mid-flight.
        $this->assertTrue($states->cancel($document));

        $document->refresh();
        $chunk->refresh();

        $this->assertSame(DocumentStatus::Outdated, $document->status, 'A document with prior working content must not land on the generic Cancelled status.');
        $this->assertTrue($chunk->is_retrievable, 'Cancelling a reindex must never withdraw chunks that already worked.');
        $this->assertSame(KnowledgeChunk::EMBEDDING_READY, $chunk->embedding_status);

        tenancy()->end();
    }

    /**
     * EmbeddingService previously only caught EmbeddingException — a provider
     * driver bug or config error surfacing as a raw Throwable propagated out
     * of embedPending() uncaught, with nothing ever recorded. It must now be
     * wrapped, logged as a KnowledgeFailure attached to the right document,
     * and rethrown so the job-level retry/backoff still applies.
     */
    public function test_unexpected_provider_exception_is_wrapped_logged_and_not_silently_lost(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content whose provider blows up unexpectedly.');
        $this->createPendingChunks($base, $document, 1);

        app()->instance(EmbeddingProviderFactory::class, new FakeEmbeddingProviderFactory(
            new ThrowingEmbeddingProvider
        ));

        $threw = null;

        try {
            app(EmbeddingService::class)->embedPending($base, limit: 10);
        } catch (\Throwable $e) {
            $threw = $e;
        }

        $this->assertInstanceOf(EmbeddingException::class, $threw, 'An unrecognised provider exception must be wrapped, not left as a raw Throwable, and must still propagate so the job retries.');
        $this->assertDatabaseHas('knowledge_failures', [
            'knowledge_document_id' => $document->id,
            'knowledge_base_id' => $base->id,
            'stage' => ProcessingStage::Embedding->value,
        ]);
        $this->assertSame(KnowledgeChunk::EMBEDDING_PENDING, KnowledgeChunk::where('owner_key', $document->chunkOwnerKey())->first()->embedding_status, 'An unrecognised (possibly transient) failure must leave the chunk pending, not mark it failed.');

        tenancy()->end();
    }

    /**
     * A base with more pending chunks than one embedPending() call's limit
     * must still fully drain — the unique-dispatch lock must never cause the
     * remainder to be silently dropped.
     */
    public function test_more_than_the_batch_limit_of_pending_chunks_eventually_drains(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Document with a very large chunk backlog.');
        $total = 2050;
        $this->createPendingChunks($base, $document, $total);

        app()->instance(EmbeddingProviderFactory::class, new FakeEmbeddingProviderFactory(
            new PredicateEmbeddingProvider($base->embedding_dimensions, fn () => false)
        ));
        config(['knowledge.embeddings.batch_size' => 200]);

        $embedded = 0;

        // Simulates what real async queue workers do across several actual
        // job executions — each call is independently bounded by the same
        // limit=2000 the real job uses, so this is a faithful drain, not a
        // single unbounded loop.
        for ($i = 0; $i < 5 && $embedded < $total; $i++) {
            $result = app(EmbeddingService::class)->embedPending($base, limit: 2000);
            $embedded += $result['embedded'];
        }

        $this->assertSame($total, $embedded);
        $this->assertSame(0, app(EmbeddingService::class)->pendingCount($base));
        $this->assertSame($total, KnowledgeChunk::where('owner_key', $document->chunkOwnerKey())->where('embedding_status', KnowledgeChunk::EMBEDDING_READY)->count());

        tenancy()->end();
    }

    /**
     * The chunk upsert previously excluded embedding_status/is_retrievable
     * from its ON DUPLICATE KEY UPDATE columns, so a chunk whose content
     * changed kept its old vector's `ready`/retrievable state — silently
     * serving a stale embedding for new content forever.
     */
    public function test_changed_chunk_content_is_marked_pending_and_loses_its_stale_vector(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Original content that will be embedded once.');
        $job = $this->createProcessingJob($document);

        ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id);

        $document->refresh();
        $this->assertSame(DocumentStatus::Ready, $document->status);
        $chunkBefore = KnowledgeChunk::where('owner_key', $document->chunkOwnerKey())->firstOrFail();
        $this->assertSame(KnowledgeChunk::EMBEDDING_READY, $chunkBefore->embedding_status);
        $this->assertNotNull($chunkBefore->embedding);

        // Content changes and the document is reprocessed (force bypasses the
        // unchanged-content short circuit).
        $document->forceFill(['extracted_text' => 'Completely different content after an edit.', 'content_hash' => null])->save();
        $job2 = $this->createProcessingJob($document);
        $states = app(ProcessingStateMachine::class);
        $this->assertTrue($states->requeueForRetry($document));

        ProcessKnowledgeDocumentJob::dispatch($document->id, $job2->id, true);

        $chunkAfter = KnowledgeChunk::where('owner_key', $document->chunkOwnerKey())->where('chunk_index', $chunkBefore->chunk_index)->firstOrFail();

        // Immediately after chunking (before the nested embed job re-embeds
        // it under the sync driver) it must have been correctly flagged, and
        // by the time embedding finished it holds a genuinely new vector.
        $this->assertNotSame($chunkBefore->content_hash, $chunkAfter->content_hash);
        $this->assertSame(KnowledgeChunk::EMBEDDING_READY, $chunkAfter->embedding_status);
        $this->assertTrue($chunkAfter->is_retrievable);

        tenancy()->end();
    }

    /**
     * If EmbedKnowledgeBaseJob itself exhausts every retry (e.g. the provider
     * is permanently misconfigured), documents must not stay in `embedding`
     * forever — its failed() callback must reconcile them immediately rather
     * than waiting on the hourly stale-job sweep.
     */
    public function test_no_document_remains_permanently_in_embedding_when_the_job_exhausts_retries(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content stuck behind a permanently broken provider.');
        $this->createPendingChunks($base, $document, 3);
        $document->forceFill(['status' => DocumentStatus::Embedding->value, 'current_stage' => ProcessingStage::Embedding->value, 'chunk_count' => 3])->save();

        $job = new EmbedKnowledgeBaseJob($base->id);
        $job->tenantId = $tenant->id;
        $job->failed(new \RuntimeException('provider permanently misconfigured'));

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertNotSame(DocumentStatus::Embedding, $document->status);
        $this->assertNotNull($document->last_error);

        tenancy()->end();
    }

    public function test_only_one_worker_can_own_a_document_at_a_time(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);

        [$base, $document] = $this->createManualTextDocument($admin->id, 'Content two workers race to process.');
        $states = app(ProcessingStateMachine::class);

        $firstWorkerWon = $states->begin($document->fresh());
        $secondWorkerWon = $states->begin($document->fresh());

        $this->assertTrue($firstWorkerWon);
        $this->assertFalse($secondWorkerWon, 'A second worker must stand down rather than also owning the document.');

        tenancy()->end();
    }

    public function test_embedding_job_lock_key_is_scoped_per_tenant_not_just_base_id(): void
    {
        $jobA = new EmbedKnowledgeBaseJob(4);
        $jobA->tenantId = 'tenant-a';

        $jobB = new EmbedKnowledgeBaseJob(4);
        $jobB->tenantId = 'tenant-b';

        $this->assertNotSame($jobA->uniqueId(), $jobB->uniqueId(), 'The same base ID in two tenants must not share a lock key.');
        $this->assertStringContainsString('tenant-a', $jobA->uniqueId());
        $this->assertStringContainsString('tenant-b', $jobB->uniqueId());
    }

    /** @return array{0: KnowledgeBase, 1: KnowledgeDocument} */
    private function createManualTextDocument(int $actorId, string $text): array
    {
        $base = $this->createBase($actorId);

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $base->id,
            'source_type' => SourceType::ManualText->value,
            'name' => 'Manual entries',
            'status' => SourceStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        $document = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'title' => 'Test document '.Str::random(6),
            'kind' => KnowledgeDocument::KIND_MANUAL_TEXT,
            'extracted_text' => $text,
            'status' => DocumentStatus::Queued->value,
            'created_by' => $actorId,
        ]);

        return [$base, $document];
    }

    /** @return array{0: KnowledgeBase, 1: KnowledgeDocument} */
    private function createStoredFileDocument(int $actorId, string $extension, string $mimeType): array
    {
        $base = $this->createBase($actorId);

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $base->id,
            'source_type' => SourceType::File->value,
            'name' => 'Uploaded files',
            'status' => SourceStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        $uuid = (string) Str::uuid();

        $document = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'title' => 'Broken file',
            'kind' => KnowledgeDocument::KIND_FILE,
            'original_filename' => 'broken.'.$extension,
            'storage_disk' => 'local',
            'storage_path' => 'knowledge-tests/'.$uuid.'.'.$extension,
            'mime_type' => $mimeType,
            'extension' => $extension,
            'file_size' => 20,
            'checksum' => hash('sha256', 'broken'),
            'status' => DocumentStatus::Queued->value,
            'created_by' => $actorId,
        ]);

        return [$base, $document];
    }

    /** Bulk-inserts $count pending chunks for a document — fast enough for a >2000-row test. */
    private function createPendingChunks(KnowledgeBase $base, KnowledgeDocument $document, int $count): void
    {
        $now = now();
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $document->knowledge_source_id,
                'knowledge_document_id' => $document->id,
                'owner_key' => $document->chunkOwnerKey(),
                'chunk_index' => $i,
                'content' => 'Chunk content number '.$i,
                'content_hash' => hash('sha256', 'chunk-'.$document->id.'-'.$i),
                'token_count' => 5,
                'character_count' => 24,
                'language' => 'en',
                'metadata' => json_encode([]),
                'source_type' => SourceType::ManualText->value,
                'embedding_status' => KnowledgeChunk::EMBEDDING_PENDING,
                'is_retrievable' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('knowledge_chunks')->insert($batch);
        }
    }

    private function createBase(int $actorId): KnowledgeBase
    {
        return KnowledgeBase::create([
            'name' => 'Pipeline Test Base',
            'slug' => 'pipeline-test-base-'.Str::lower(Str::random(6)),
            'status' => KnowledgeBaseStatus::Active->value,
            'visibility' => KnowledgeVisibility::Workspace->value,
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'embedding_provider' => 'local',
            'embedding_model' => 'local-hash-embedding',
            'embedding_dimensions' => 32,
            'embedding_version' => 1,
            'chunking_strategy' => 'paragraph',
            'chunk_size' => 800,
            'chunk_overlap' => 80,
            'retrieval_mode' => 'hybrid',
            'top_k' => 5,
            'candidate_pool' => 20,
            'similarity_threshold' => 0,
            'reranking_enabled' => true,
            'max_context_tokens' => 4000,
            'allow_cross_source_retrieval' => true,
            'prefer_recent_content' => false,
            'require_citations' => true,
            'exclude_expired_content' => true,
            'created_by' => $actorId,
        ]);
    }

    private function createProcessingJob(KnowledgeDocument $document): KnowledgeProcessingJob
    {
        return KnowledgeProcessingJob::create([
            'knowledge_base_id' => $document->knowledge_base_id,
            'knowledge_source_id' => $document->knowledge_source_id,
            'knowledge_document_id' => $document->id,
            'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
            'queue' => config('knowledge.queues.processing'),
            'status' => ProcessingJobStatus::Queued->value,
            'queued_at' => now(),
            'max_attempts' => (int) config('knowledge.processing.max_attempts'),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }
}
