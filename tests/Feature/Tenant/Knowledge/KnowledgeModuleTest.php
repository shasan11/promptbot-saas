<?php

namespace Tests\Feature\Tenant\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\FaqStatus;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\ProcessingStage;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeFailure;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeAccessGrant;
use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\GranteeType;
use App\Services\Knowledge\AgentKnowledgeRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class KnowledgeModuleTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_administrator_can_create_a_knowledge_base(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        $response = $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/bases", [
            'name' => 'Customer Support Knowledge',
            'description' => 'Refund, shipping, and troubleshooting answers.',
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'visibility' => KnowledgeVisibility::Workspace->value,
            'chunking_strategy' => 'paragraph',
            'chunk_size' => 800,
            'chunk_overlap' => 80,
            'retrieval_mode' => 'hybrid',
            'top_k' => 5,
            'candidate_pool' => 20,
            'similarity_threshold' => 0.2,
            'reranking_enabled' => true,
            'max_context_tokens' => 4000,
            'allow_cross_source_retrieval' => true,
            'prefer_recent_content' => false,
            'require_citations' => true,
            'exclude_expired_content' => true,
        ]);

        $response->assertRedirect();

        tenancy()->initialize($tenant);
        $base = KnowledgeBase::where('name', 'Customer Support Knowledge')->firstOrFail();
        $this->assertSame(KnowledgeBaseStatus::Draft, $base->status);
        $this->assertSame(KnowledgeVisibility::Workspace, $base->visibility);
        $this->assertSame($admin->id, $base->created_by);
        tenancy()->end();
    }

    public function test_document_upload_creates_source_document_and_processing_job(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $baseUuid = $this->createBaseInsideTenant($tenant, $admin->id);

        $response = $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/documents", [
            'knowledge_base' => $baseUuid,
            'files' => [
                UploadedFile::fake()->createWithContent('refund-policy.txt', 'Customers can request refunds within 30 days.'),
            ],
            'on_duplicate' => 'skip',
            'tags' => ['refunds', 'policy'],
        ]);

        $response->assertRedirect();
        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);

        tenancy()->initialize($tenant);
        $source = KnowledgeSource::where('source_type', SourceType::File->value)->firstOrFail();
        $document = KnowledgeDocument::where('original_filename', 'refund-policy.txt')->firstOrFail();

        $this->assertSame($source->id, $document->knowledge_source_id);
        $this->assertSame(DocumentStatus::Queued, $document->status);
        $this->assertSame('txt', $document->extension);
        $this->assertSame(['policy', 'refunds'], $document->tags()->orderBy('name')->pluck('name')->values()->all());
        $this->assertDatabaseHas('knowledge_processing_jobs', [
            'knowledge_document_id' => $document->id,
            'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
            'status' => 'queued',
        ]);
        tenancy()->end();
    }

    public function test_published_faq_creates_retrievable_chunks(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $baseUuid = $this->createBaseInsideTenant($tenant, $admin->id);

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/faqs", [
            'knowledge_base' => $baseUuid,
            'question' => 'How long is the refund period?',
            'answer' => 'Customers may request a refund within 30 days of purchase.',
            'category' => 'Billing',
            'language' => 'en',
            'status' => FaqStatus::Published->value,
            'priority' => 10,
            'tags' => ['refunds'],
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $faq = KnowledgeFaq::where('question', 'How long is the refund period?')->firstOrFail();
        $chunk = KnowledgeChunk::where('knowledge_faq_id', $faq->id)->firstOrFail();

        $this->assertSame(FaqStatus::Published, $faq->status);
        $this->assertFalse($chunk->is_retrievable);
        $this->assertSame(KnowledgeChunk::EMBEDDING_PENDING, $chunk->embedding_status);
        $this->assertStringContainsString('refund within 30 days', $chunk->content);
        tenancy()->end();
    }

    public function test_manual_text_source_is_saved_and_queued_for_processing(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $baseUuid = $this->createBaseInsideTenant($tenant, $admin->id);

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/text-sources", [
            'knowledge_base' => $baseUuid,
            'title' => 'Escalation policy',
            'content' => 'Escalate urgent billing disputes to the support manager within one business hour.',
            'language' => 'en',
            'tags' => ['support', 'policy'],
        ])->assertRedirect();

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);

        tenancy()->initialize($tenant);
        $source = KnowledgeSource::where('source_type', SourceType::ManualText->value)->firstOrFail();
        $document = KnowledgeDocument::where('title', 'Escalation policy')->firstOrFail();

        $this->assertSame($source->id, $document->knowledge_source_id);
        $this->assertSame(KnowledgeDocument::KIND_MANUAL_TEXT, $document->kind);
        $this->assertSame(DocumentStatus::Queued, $document->status);
        $this->assertSame('Escalate urgent billing disputes to the support manager within one business hour.', KnowledgeDocument::whereKey($document->id)->value('extracted_text'));
        $this->assertDatabaseHas('knowledge_processing_jobs', [
            'knowledge_document_id' => $document->id,
            'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
            'status' => 'queued',
        ]);
        tenancy()->end();
    }

    public function test_retrieval_playground_returns_only_allowed_tenant_knowledge(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB] = $this->createTenantWithDomain();
        $adminA = $this->createTenantUser($tenantA);
        $adminB = $this->createTenantUser($tenantB);

        [$baseAUuid] = $this->createRetrievableChunkInsideTenant(
            $tenantA,
            $adminA->id,
            'Customers can request refunds within 30 days of purchase.',
        );
        $this->createRetrievableChunkInsideTenant(
            $tenantB,
            $adminB->id,
            'Tenant B secret cancellation policy is never visible to tenant A.',
        );

        $response = $this->actingAs($adminA, 'tenant')->postJson("http://{$domainA}/knowledge/playground/retrieve", [
            'query' => 'customers refunds',
            'knowledge_bases' => [$baseAUuid],
            'mode' => 'keyword',
            'top_k' => 5,
            'similarity_threshold' => 0,
            'generate_answer' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('zero_results', false);
        $this->assertNotEmpty($response->json('answer_preview.answer'));
        $this->assertNotEmpty($response->json('answer_preview.sources_used'));

        $contents = collect($response->json('results'))->pluck('content')->implode("\n");
        $this->assertStringContainsString('refunds within 30 days', $contents);
        $this->assertStringNotContainsString('Tenant B secret', $contents);
    }

    public function test_agent_retrieval_requires_explicit_knowledge_grant(): void
    {
        [$tenant] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);
        [$baseUuid] = $this->createRetrievableChunkInsideTenantWithoutTenancyReset($admin->id, 'Customers can request refunds within 30 days.');
        $base = KnowledgeBase::where('uuid', $baseUuid)->firstOrFail();

        $service = app(AgentKnowledgeRetrievalService::class);
        $this->assertTrue($service->retrieve('support-agent', 'customers refunds', ['mode' => 'keyword', 'similarity_threshold' => 0])->isEmpty());

        KnowledgeAccessGrant::create([
            'knowledge_base_id' => $base->id,
            'grantee_type' => GranteeType::Agent->value,
            'grantee_key' => 'support-agent',
            'access_level' => AccessLevel::Read->value,
            'granted_by' => $admin->id,
        ]);

        $outcome = $service->retrieve('support-agent', 'customers refunds', ['mode' => 'keyword', 'similarity_threshold' => 0]);
        $this->assertFalse($outcome->isEmpty());
        $this->assertStringContainsString('refunds within 30 days', $outcome->hits[0]->chunk->content);
        tenancy()->end();
    }

    public function test_disabling_source_withdraws_chunks_from_retrieval(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);
        [$baseUuid, $chunkUuid] = $this->createRetrievableChunkInsideTenantWithoutTenancyReset($admin->id, 'Customers can request refunds within 30 days.');
        $source = KnowledgeSource::firstOrFail();
        tenancy()->end();

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/knowledge/sources/{$source->uuid}/disable")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertFalse(KnowledgeChunk::where('uuid', $chunkUuid)->firstOrFail()->is_retrievable);
        $this->assertSame(SourceStatus::Disabled, $source->refresh()->status);
        tenancy()->end();
    }

    public function test_deleting_source_withdraws_chunks_from_retrieval(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);
        [, $chunkUuid] = $this->createRetrievableChunkInsideTenantWithoutTenancyReset($admin->id, 'Customers can request refunds within 30 days.');
        $source = KnowledgeSource::firstOrFail();
        tenancy()->end();

        $this->actingAs($admin, 'tenant')
            ->delete("http://{$domain}/knowledge/sources/{$source->uuid}")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertFalse(KnowledgeChunk::where('uuid', $chunkUuid)->firstOrFail()->is_retrievable);
        $this->assertNotNull($source->refresh()->deleted_at);
        tenancy()->end();
    }

    public function test_tenant_cannot_open_another_tenants_knowledge_base_uuid(): void
    {
        [$tenantA, $domainA] = $this->createTenantWithDomain();
        [$tenantB] = $this->createTenantWithDomain();
        $adminA = $this->createTenantUser($tenantA);
        $adminB = $this->createTenantUser($tenantB);
        $baseBUuid = $this->createBaseInsideTenant($tenantB, $adminB->id);

        $this->actingAs($adminA, 'tenant')
            ->get("http://{$domainA}/knowledge/bases/{$baseBUuid}")
            ->assertNotFound();
    }

    public function test_signed_download_url_still_requires_document_permission(): void
    {
        Storage::fake('local');

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $viewer = $this->createTenantUser($tenant, ['email' => 'viewer@example.test'], 'Viewer');

        tenancy()->initialize($tenant);
        [$base, $document] = $this->createStoredFileDocument($admin->id);
        tenancy()->end();

        URL::forceRootUrl("http://{$domain}");
        $tenantUrl = URL::temporarySignedRoute(
            'tenant.admin.knowledge.documents.download',
            now()->addMinutes(5),
            ['document' => $document->uuid]
        );
        URL::forceRootUrl(null);

        $this->actingAs($viewer, 'tenant')
            ->get($tenantUrl)
            ->assertForbidden();

        $this->actingAs($admin, 'tenant')
            ->get($tenantUrl)
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_failure_retry_requeues_document_and_resolves_failure(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);

        tenancy()->initialize($tenant);
        [$base, $document] = $this->createStoredFileDocument($admin->id);
        $document->forceFill([
            'status' => DocumentStatus::Failed->value,
            'current_stage' => ProcessingStage::Extracting->value,
            'last_error' => 'Extraction failed.',
            'failure_stage' => ProcessingStage::Extracting->value,
            'failure_category' => FailureCategory::ExtractionError->value,
        ])->save();
        $failure = KnowledgeFailure::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $document->knowledge_source_id,
            'knowledge_document_id' => $document->id,
            'stage' => ProcessingStage::Extracting->value,
            'category' => FailureCategory::ExtractionError->value,
            'message' => 'We could not read text from this file.',
            'attempt' => 1,
            'retryable' => true,
        ]);
        tenancy()->end();

        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/knowledge/failed/{$failure->uuid}/retry")
            ->assertRedirect();

        Queue::assertPushed(ProcessKnowledgeDocumentJob::class);

        tenancy()->initialize($tenant);
        $this->assertSame(DocumentStatus::Queued, $document->refresh()->status);
        $this->assertDatabaseHas('knowledge_processing_jobs', [
            'knowledge_document_id' => $document->id,
            'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
            'status' => 'queued',
            'created_by' => $admin->id,
        ]);
        $this->assertNotNull($failure->refresh()->resolved_at);
        tenancy()->end();
    }

    private function createBaseInsideTenant($tenant, int $actorId): string
    {
        tenancy()->initialize($tenant);

        try {
            return KnowledgeBase::create([
                'name' => 'Customer Support Knowledge',
                'slug' => 'customer-support-knowledge-'.Str::lower(Str::random(6)),
                'description' => 'Support articles.',
                'status' => KnowledgeBaseStatus::Active->value,
                'visibility' => KnowledgeVisibility::Workspace->value,
                'default_language' => 'en',
                'supported_languages' => ['en'],
                'embedding_provider' => 'local',
                'embedding_model' => 'local-hash-embedding',
                'embedding_dimensions' => 256,
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
            ])->uuid;
        } finally {
            tenancy()->end();
        }
    }

    /** @return array{0: KnowledgeBase, 1: KnowledgeDocument} */
    private function createStoredFileDocument(int $actorId): array
    {
        $base = KnowledgeBase::create([
            'name' => 'Downloadable Knowledge',
            'slug' => 'downloadable-knowledge-'.Str::lower(Str::random(6)),
            'status' => KnowledgeBaseStatus::Active->value,
            'visibility' => KnowledgeVisibility::Workspace->value,
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'embedding_provider' => 'local',
            'embedding_model' => 'local-hash-embedding',
            'embedding_dimensions' => 256,
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

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $base->id,
            'source_type' => SourceType::File->value,
            'name' => 'Uploaded files',
            'status' => SourceStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        $document = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'title' => 'Refund policy',
            'kind' => KnowledgeDocument::KIND_FILE,
            'original_filename' => 'refund-policy.txt',
            'storage_disk' => 'local',
            'storage_path' => 'knowledge-tests/refund-policy.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'file_size' => 35,
            'checksum' => hash('sha256', 'refund policy'),
            'status' => DocumentStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        Storage::disk('local')->put('knowledge-tests/refund-policy.txt', 'Customers can request refunds in 30 days.');

        return [$base, $document];
    }

    /** @return array{0: string, 1: string} */
    private function createRetrievableChunkInsideTenant($tenant, int $actorId, string $content): array
    {
        tenancy()->initialize($tenant);

        try {
            $base = KnowledgeBase::create([
                'name' => 'Retrievable Support Knowledge',
                'slug' => 'retrievable-support-knowledge-'.Str::lower(Str::random(6)),
                'status' => KnowledgeBaseStatus::Active->value,
                'visibility' => KnowledgeVisibility::Workspace->value,
                'default_language' => 'en',
                'supported_languages' => ['en'],
                'embedding_provider' => 'local',
                'embedding_model' => 'local-hash-embedding',
                'embedding_dimensions' => 256,
                'embedding_version' => 1,
                'chunking_strategy' => 'paragraph',
                'chunk_size' => 800,
                'chunk_overlap' => 80,
                'retrieval_mode' => 'keyword',
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
                'name' => 'Manual policy',
                'status' => SourceStatus::Ready->value,
                'created_by' => $actorId,
            ]);

            $chunk = KnowledgeChunk::create([
                'knowledge_base_id' => $base->id,
                'knowledge_source_id' => $source->id,
                'owner_key' => 'manual-test:'.$base->id,
                'chunk_index' => 0,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'token_count' => 12,
                'character_count' => strlen($content),
                'language' => 'en',
                'metadata' => ['document_name' => 'Refund policy'],
                'source_type' => SourceType::ManualText->value,
                'is_retrievable' => true,
            ]);

            $chunk->setVector(array_fill(0, 256, 0.1), 'local', 'local-hash-embedding', 1);
            $chunk->save();

            return [$base->uuid, $chunk->uuid];
        } finally {
            tenancy()->end();
        }
    }

    private function createRetrievableChunkInsideTenantWithoutTenancyReset(int $actorId, string $content): array
    {
        $base = KnowledgeBase::create([
            'name' => 'Agent Support Knowledge',
            'slug' => 'agent-support-knowledge-'.Str::lower(Str::random(6)),
            'status' => KnowledgeBaseStatus::Active->value,
            'visibility' => KnowledgeVisibility::Private->value,
            'default_language' => 'en',
            'supported_languages' => ['en'],
            'embedding_provider' => 'local',
            'embedding_model' => 'local-hash-embedding',
            'embedding_dimensions' => 256,
            'embedding_version' => 1,
            'chunking_strategy' => 'paragraph',
            'chunk_size' => 800,
            'chunk_overlap' => 80,
            'retrieval_mode' => 'keyword',
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
            'name' => 'Agent policy',
            'status' => SourceStatus::Ready->value,
            'created_by' => $actorId,
        ]);

        $chunk = KnowledgeChunk::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'owner_key' => 'agent-test:'.$base->id,
            'chunk_index' => 0,
            'content' => $content,
            'content_hash' => hash('sha256', $content),
            'token_count' => 12,
            'character_count' => strlen($content),
            'language' => 'en',
            'metadata' => ['document_name' => 'Agent policy'],
            'source_type' => SourceType::ManualText->value,
            'is_retrievable' => true,
        ]);
        $chunk->setVector(array_fill(0, 256, 0.1), 'local', 'local-hash-embedding', 1);
        $chunk->save();

        return [$base->uuid, $chunk->uuid];
    }
}
