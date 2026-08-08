<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Base module — Phase 1 core domain.
 *
 * These tables live on the *tenant* database. PromptBot runs stancl/tenancy in
 * database-per-tenant mode, so there is deliberately no `tenant_id` column
 * anywhere in this module: isolation is physical, enforced by the connection,
 * and cannot be defeated by a forgotten global scope or a crafted ID in a
 * request. Cross-tenant access would require a connection swap, which only
 * tenancy()->initialize() performs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_bases', function (Blueprint $table): void {
            $table->id();
            // Public identifier. Every externally addressable knowledge resource
            // is referenced by UUID so sequential IDs never leak record counts.
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('visibility')->default('workspace');
            $table->string('icon')->nullable();
            $table->string('color', 32)->nullable();

            // --- Language -------------------------------------------------
            $table->string('default_language', 12)->default('en');
            $table->json('supported_languages')->nullable();

            // --- Embedding configuration ---------------------------------
            // Dimensions are pinned per base: vectors of differing widths can
            // never be compared, so a model change requires an explicit
            // re-index rather than silently corrupting similarity scores.
            $table->string('embedding_provider')->default('local');
            $table->string('embedding_model')->default('promptbot-local-hash-v1');
            $table->unsignedSmallInteger('embedding_dimensions')->default(384);
            $table->unsignedInteger('embedding_version')->default(1);

            // --- Chunking configuration -----------------------------------
            $table->string('chunking_strategy')->default('heading');
            $table->unsignedSmallInteger('chunk_size')->default(512);
            $table->unsignedSmallInteger('chunk_overlap')->default(64);

            // --- Retrieval configuration ----------------------------------
            $table->string('retrieval_mode')->default('hybrid');
            $table->unsignedSmallInteger('top_k')->default(5);
            $table->unsignedSmallInteger('candidate_pool')->default(20);
            $table->decimal('similarity_threshold', 4, 3)->default(0.700);
            $table->boolean('reranking_enabled')->default(true);
            $table->unsignedInteger('max_context_tokens')->default(8000);
            $table->boolean('allow_cross_source_retrieval')->default(true);
            $table->boolean('prefer_recent_content')->default(false);
            $table->boolean('require_citations')->default(true);
            $table->boolean('exclude_expired_content')->default(true);

            // --- Freshness policy ------------------------------------------
            $table->unsignedSmallInteger('review_every_days')->nullable();

            // --- Denormalised counters -------------------------------------
            // Maintained by KnowledgeStatisticsService. Counting chunks live on
            // every page load is the single most expensive query in the module.
            $table->unsignedInteger('source_count')->default(0);
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);
            $table->unsignedInteger('failed_source_count')->default(0);

            $table->timestamp('last_indexed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('counters_refreshed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'updated_at'], 'kb_status_updated_at_idx');
            $table->index('visibility', 'kb_visibility_idx');
        });

        Schema::create('knowledge_collections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('knowledge_collections')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            // Materialised depth so the 5-level nesting cap can be enforced with
            // a single check instead of walking ancestors on every insert.
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->unsignedInteger('document_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['knowledge_base_id', 'slug'], 'kcol_base_slug_uniq');
            $table->index(['knowledge_base_id', 'parent_id', 'sort_order'], 'kcol_base_parent_sort_order_idx');
        });

        Schema::create('knowledge_tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('knowledge_taggables', function (Blueprint $table): void {
            $table->foreignId('knowledge_tag_id')->constrained('knowledge_tags')->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->primary(['knowledge_tag_id', 'taggable_type', 'taggable_id'], 'knowledge_taggables_primary');
            $table->index(['taggable_type', 'taggable_id'], 'knowledge_taggables_taggable_index');
        });

        /**
         * Explicit access grants layered on top of a knowledge base's
         * `visibility`. A grant may target a user, team, role, or AI agent, and
         * may be scoped to a single collection. Retrieval reads this table to
         * build the allow-list of knowledge base / collection IDs *before*
         * searching, rather than filtering confidential chunks out afterwards.
         */
        Schema::create('knowledge_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->cascadeOnDelete();
            $table->string('grantee_type', 32);
            // Nullable because agent grants reference an identifier that has no
            // table yet; `grantee_key` carries it until the agents module lands.
            $table->unsignedBigInteger('grantee_id')->nullable();
            $table->string('grantee_key')->nullable();
            $table->string('grantee_label')->nullable();
            $table->string('access_level', 32)->default('read');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Collection scope, grantee_id and grantee_key are each optional, and
            // MySQL counts NULLs in a unique index as distinct — so a plain
            // composite unique would happily store the same grant many times.
            // The hash of the full tuple is what actually enforces uniqueness.
            $table->char('dedupe_key', 64)->unique();

            $table->index(['knowledge_base_id', 'knowledge_collection_id'], 'knowledge_access_grants_scope_index');
            $table->index(['grantee_type', 'grantee_id'], 'knowledge_access_grants_grantee_index');
            $table->index(['grantee_type', 'grantee_key'], 'knowledge_access_grants_grantee_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_access_grants');
        Schema::dropIfExists('knowledge_taggables');
        Schema::dropIfExists('knowledge_tags');
        Schema::dropIfExists('knowledge_collections');
        Schema::dropIfExists('knowledge_bases');
    }
};
