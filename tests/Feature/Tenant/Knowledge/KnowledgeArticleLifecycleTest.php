<?php

namespace Tests\Feature\Tenant\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\Knowledge\GranteeType;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Models\Knowledge\KnowledgeAccessGrant;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeChunk;
use App\Services\Knowledge\AgentKnowledgeRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class KnowledgeArticleLifecycleTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    public function test_article_walks_the_full_review_lifecycle(): void
    {
        Queue::fake();

        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $reviewer = $this->createTenantUser($tenant);
        $baseUuid = $this->createBaseInsideTenant($tenant, $admin->id);

        // 1. Create as a draft — must not be retrievable or chunked yet.
        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/articles", [
            'knowledge_base' => $baseUuid,
            'title' => 'Refund policy',
            'summary' => 'How refunds work.',
            'body' => 'Customers may request a refund within 30 days of purchase.',
            'language' => 'en',
            'tags' => ['refunds'],
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $article = KnowledgeArticle::where('title', 'Refund policy')->firstOrFail();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame(0, KnowledgeChunk::where('owner_key', $article->chunkOwnerKey())->count());
        tenancy()->end();

        // 2. Submit for review.
        $this->actingAs($admin, 'tenant')
            ->post("http://{$domain}/knowledge/articles/{$article->uuid}/submit-for-review")
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(ArticleStatus::InReview, $article->refresh()->status);
        tenancy()->end();

        // 3. Reject with a note — back to Draft, still not retrievable.
        $this->actingAs($reviewer, 'tenant')
            ->post("http://{$domain}/knowledge/articles/{$article->uuid}/reject", ['review_note' => 'Please cite the exact policy section.'])
            ->assertRedirect();

        tenancy()->initialize($tenant);
        $article->refresh();
        $this->assertSame(ArticleStatus::Draft, $article->status);
        $this->assertSame('Please cite the exact policy section.', $article->review_note);
        $this->assertSame(0, KnowledgeChunk::where('owner_key', $article->chunkOwnerKey())->count());
        tenancy()->end();

        // 4. Submit again and approve — now it must be chunked and retrievable, with a citation.
        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/articles/{$article->uuid}/submit-for-review")->assertRedirect();
        $this->actingAs($reviewer, 'tenant')->post("http://{$domain}/knowledge/articles/{$article->uuid}/approve")->assertRedirect();

        tenancy()->initialize($tenant);
        $article->refresh();
        $this->assertSame(ArticleStatus::Published, $article->status);
        $this->assertNotNull($article->published_at);
        $chunk = KnowledgeChunk::where('owner_key', $article->chunkOwnerKey())->firstOrFail();
        $this->assertStringContainsString('refund within 30 days', $chunk->content);
        $this->assertSame('Refund policy', $chunk->citation()['article_title']);

        // Chunks start pending until the embedding job runs — the FAQ/document
        // convention this mirrors — but they must already carry the article FK.
        $this->assertSame($article->id, $chunk->knowledge_article_id);
        tenancy()->end();

        // 5. Archive — chunks must withdraw from retrieval immediately.
        $this->actingAs($reviewer, 'tenant')->post("http://{$domain}/knowledge/articles/{$article->uuid}/archive")->assertRedirect();

        tenancy()->initialize($tenant);
        $article->refresh();
        $this->assertSame(ArticleStatus::Archived, $article->status);
        $this->assertSame(0, KnowledgeChunk::where('owner_key', $article->chunkOwnerKey())->count());
        tenancy()->end();

        // 6. Restore — lands back on Draft, not Published: it must be re-reviewed.
        $this->actingAs($reviewer, 'tenant')->post("http://{$domain}/knowledge/articles/{$article->uuid}/restore")->assertRedirect();

        tenancy()->initialize($tenant);
        $this->assertSame(ArticleStatus::Draft, $article->refresh()->status);
        tenancy()->end();
    }

    public function test_draft_and_in_review_articles_never_reach_agent_retrieval(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();
        $admin = $this->createTenantUser($tenant);
        $baseUuid = $this->createBaseInsideTenant($tenant, $admin->id);

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/articles", [
            'knowledge_base' => $baseUuid,
            'title' => 'Internal escalation steps',
            'body' => 'Escalate any payment dispute over $500 to the finance team directly.',
            'language' => 'en',
        ])->assertRedirect();

        tenancy()->initialize($tenant);
        $article = KnowledgeArticle::where('title', 'Internal escalation steps')->firstOrFail();
        $base = KnowledgeBase::where('uuid', $baseUuid)->firstOrFail();

        // Full read access to the base, granted directly — the article being
        // unapproved must be what keeps it out of results, not a missing grant.
        KnowledgeAccessGrant::create([
            'knowledge_base_id' => $base->id,
            'grantee_type' => GranteeType::Agent->value,
            'grantee_key' => 'support-agent',
            'access_level' => AccessLevel::Read->value,
            'granted_by' => $admin->id,
        ]);

        $service = app(AgentKnowledgeRetrievalService::class);
        $draftOutcome = $service->retrieve('support-agent', 'escalation steps', ['mode' => 'keyword', 'similarity_threshold' => 0]);
        $this->assertTrue($draftOutcome->isEmpty());
        tenancy()->end();

        $this->actingAs($admin, 'tenant')->post("http://{$domain}/knowledge/articles/{$article->uuid}/submit-for-review")->assertRedirect();

        tenancy()->initialize($tenant);
        $inReviewOutcome = $service->retrieve('support-agent', 'escalation steps', ['mode' => 'keyword', 'similarity_threshold' => 0]);
        $this->assertTrue($inReviewOutcome->isEmpty(), 'An in-review article must not be answerable by an agent.');
        tenancy()->end();
    }

    public function test_tenant_cannot_reach_another_tenants_published_article(): void
    {
        [$tenantA] = $this->createTenantWithDomain();
        [$tenantB, $domainB] = $this->createTenantWithDomain();
        $adminA = $this->createTenantUser($tenantA);
        $adminB = $this->createTenantUser($tenantB);
        $baseBUuid = $this->createBaseInsideTenant($tenantB, $adminB->id);

        $this->actingAs($adminB, 'tenant')->post("http://{$domainB}/knowledge/articles", [
            'knowledge_base' => $baseBUuid,
            'title' => 'Tenant B confidential policy',
            'body' => 'Tenant B secret refund terms.',
            'language' => 'en',
        ])->assertRedirect();

        tenancy()->initialize($tenantB);
        $articleB = KnowledgeArticle::where('title', 'Tenant B confidential policy')->firstOrFail();
        tenancy()->end();

        // Tenant A resolves an article UUID that only exists in Tenant B's
        // database — Tenant A's tenancy context has no matching row at all,
        // which is the isolation guarantee (a separate database per tenant,
        // not a shared table filtered by a foreign key).
        tenancy()->initialize($tenantA);
        $this->assertNull(KnowledgeArticle::where('uuid', $articleB->uuid)->first());
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
}
