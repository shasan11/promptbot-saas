<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Base module — sources and the content they produce.
 *
 * Shape of the domain:
 *   knowledge_sources     — where knowledge came from (a file batch, a site, an FAQ set)
 *   knowledge_documents   — one ingestible item: an uploaded file, a manual text
 *                           article, or the body of one crawled page
 *   knowledge_website_pages — crawl bookkeeping (URLs, hashes, HTTP status)
 *   knowledge_faqs        — structured Q&A, embedded one pair per chunk
 *   knowledge_chunks      — the retrieval unit; the only table search reads
 *
 * Manual text deliberately does NOT get its own table. A manual article is a
 * document with no stored file, which keeps versioning, chunking, tagging and
 * retrieval on exactly one code path instead of two near-identical ones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->nullOnDelete();

            $table->string('source_type', 32);
            $table->string('name');
            $table->text('description')->nullable();

            // Type-specific settings (crawl rules, sitemap URL, connector options).
            // Never contains secrets — those live in knowledge_source_credentials.
            $table->json('configuration')->nullable();

            $table->string('status')->default('pending');
            $table->string('priority', 32)->default('normal');

            // --- Synchronisation ------------------------------------------
            $table->string('sync_status')->default('never_synced');
            $table->string('sync_frequency', 32)->default('manual');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable();
            $table->unsignedInteger('consecutive_failure_count')->default(0);

            // --- Freshness -------------------------------------------------
            $table->unsignedSmallInteger('review_every_days')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            // --- Health ----------------------------------------------------
            // `last_error` is operator-facing prose; the raw exception detail is
            // kept on knowledge_failures so we never render a stack trace to a
            // support agent.
            $table->text('last_error')->nullable();
            $table->string('last_failure_stage', 32)->nullable();
            $table->string('last_failure_category', 48)->nullable();
            $table->unsignedInteger('retry_count')->default(0);

            // --- Denormalised counters -------------------------------------
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedBigInteger('storage_bytes')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['knowledge_base_id', 'status'], 'ksrc_base_status_idx');
            $table->index(['source_type', 'status'], 'ksrc_source_type_status_idx');
            $table->index(['sync_status', 'next_sync_at'], 'ksrc_sync_status_next_sync_at_idx');
            $table->index('review_due_at', 'ksrc_review_due_at_idx');
        });

        /**
         * Credentials for connector-backed sources, split out so that the row a
         * list query loads never carries a secret. `secret` is encrypted at the
         * application layer (Eloquent `encrypted:array` cast) — the column holds
         * ciphertext, and it is excluded from every API resource.
         */
        Schema::create('knowledge_source_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('external_account_label')->nullable();
            $table->text('secret');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->string('validation_status', 32)->default('unknown');
            $table->timestamps();

            $table->unique(['knowledge_source_id', 'provider'], 'kcred_source_provider_uniq');
        });

        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->nullOnDelete();

            $table->string('title');
            // 'file' | 'manual_text' | 'website_page' — decides which extractor
            // runs and whether `storage_path` is expected to be populated.
            $table->string('kind', 32)->default('file');

            // --- Original file (null for manual text and crawled pages) -----
            $table->string('original_filename')->nullable();
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            // SHA-256 of the raw bytes; the primary duplicate-detection signal
            // for uploads and the reason a re-upload of an identical file is
            // free rather than re-embedded.
            $table->char('checksum', 64)->nullable();

            // --- Extracted content -----------------------------------------
            $table->longText('extracted_text')->nullable();
            // SHA-256 of normalised extracted text. Two different PDFs that
            // render the same prose share this, so change detection on re-crawl
            // and re-upload both key off it.
            $table->char('content_hash', 64)->nullable();
            $table->json('structure')->nullable(); // pages, headings, tables
            $table->string('language', 12)->nullable();
            $table->unsignedInteger('character_count')->default(0);
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->unsignedInteger('token_estimate')->default(0);
            $table->boolean('ocr_applied')->default(false);
            $table->boolean('has_tables')->default(false);

            // --- Lifecycle ---------------------------------------------------
            $table->string('status')->default('uploaded');
            $table->string('current_stage', 32)->nullable();
            $table->timestamp('processing_started_at')->nullable();
            $table->timestamp('processing_completed_at')->nullable();
            $table->unsignedInteger('processing_duration_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->string('failure_stage', 32)->nullable();
            $table->string('failure_category', 48)->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('indexed_at')->nullable();

            // --- Versioning ---------------------------------------------------
            $table->unsignedInteger('version_number')->default(1);
            // Points at the version whose chunks are currently live. A
            // replacement processes into a new version and only flips this
            // pointer once it reaches `ready`, so retrieval never has a gap.
            $table->foreignId('active_version_id')->nullable();

            // --- Freshness ------------------------------------------------------
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamp('review_due_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['knowledge_base_id', 'status'], 'kdoc_base_status_idx');
            $table->index(['knowledge_source_id', 'status'], 'kdoc_source_status_idx');
            $table->index(['knowledge_collection_id'], 'kdoc_collection_idx');
            $table->index(['status', 'created_at'], 'kdoc_status_created_at_idx');
            $table->index('checksum', 'kdoc_checksum_idx');
            $table->index('content_hash', 'kdoc_content_hash_idx');
            $table->index('language', 'kdoc_language_idx');
            $table->index('review_due_at', 'kdoc_review_due_at_idx');
        });

        Schema::create('knowledge_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')->nullable()->constrained('knowledge_document_versions')->nullOnDelete();

            $table->string('title');
            $table->string('original_filename')->nullable();
            $table->string('storage_disk', 64)->nullable();
            $table->string('storage_path', 1024)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('checksum', 64)->nullable();
            // Historical prose is retained for diffing and restore. Historical
            // *embeddings* are not — they are regenerated on restore, which is
            // far cheaper than storing a vector set per version forever.
            $table->longText('extracted_text')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('character_count')->default(0);
            $table->string('change_summary')->nullable();
            $table->boolean('is_active')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_document_id', 'version_number'], 'kdocv_document_version_number_uniq');
            $table->index(['knowledge_document_id', 'is_active'], 'kdocv_document_is_active_idx');
        });

        Schema::create('knowledge_website_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            // The extracted body becomes a document so that chunking, tagging
            // and versioning behave identically to an uploaded file.
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->nullOnDelete();

            $table->text('url');
            $table->text('canonical_url')->nullable();
            // URLs exceed MySQL's index width, so uniqueness and lookups run off
            // a hash of the *normalised* URL (tracking params stripped, fragment
            // dropped, trailing slash folded) — that normalisation is what stops
            // the same page being crawled a dozen times.
            $table->char('url_hash', 64);
            $table->string('page_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('depth')->default(0);
            $table->string('language', 12)->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->string('status')->default('discovered');
            $table->text('last_error')->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamp('missing_since')->nullable();

            $table->timestamps();

            $table->unique(['knowledge_source_id', 'url_hash'], 'kpage_source_url_hash_uniq');
            $table->index(['knowledge_source_id', 'status'], 'kpage_source_status_idx');
            $table->index('content_hash', 'kpage_content_hash_idx');
        });

        Schema::create('knowledge_faqs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->nullOnDelete();

            $table->text('question');
            $table->longText('answer');
            $table->string('category')->nullable();
            $table->string('language', 12)->default('en');
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('indexed_at')->nullable();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['knowledge_base_id', 'status'], 'kfaq_base_status_idx');
            $table->index(['category', 'status'], 'kfaq_category_status_idx');
            $table->index('content_hash', 'kfaq_content_hash_idx');
        });

        Schema::create('knowledge_faq_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_faq_id')->constrained('knowledge_faqs')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('question');
            $table->longText('answer');
            $table->string('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_faq_id', 'version_number'], 'kfaqv_faq_version_number_uniq');
        });

        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->nullOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('knowledge_website_page_id')->nullable()->constrained('knowledge_website_pages')->cascadeOnDelete();
            $table->foreignId('knowledge_faq_id')->nullable()->constrained('knowledge_faqs')->cascadeOnDelete();

            // Identifies the owning record across the three possible parents
            // ("document:12", "faq:5", "page:97"). Paired with chunk_index it
            // gives the upsert key that makes re-processing idempotent: a worker
            // that runs twice overwrites the same rows instead of duplicating
            // them. A composite unique over the three nullable parent FKs would
            // not work — MySQL treats NULLs as distinct, so every re-run would
            // insert afresh.
            $table->string('owner_key', 64);
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            // Deliberately NOT unique. Boilerplate ("© Acme Corp — all rights
            // reserved") legitimately recurs across documents, and silently
            // dropping the second copy would leave a document missing a chunk.
            // Duplicate detection reports on this; it does not enforce on it.
            $table->char('content_hash', 64);
            $table->unsignedInteger('token_count')->default(0);
            $table->unsignedInteger('character_count')->default(0);
            $table->string('language', 12)->nullable();

            // Citation payload: document title, page number, heading, URL, etc.
            // Denormalised so building a citation never needs a join fan-out
            // across four possible parent tables.
            $table->json('metadata')->nullable();

            // --- Vector -------------------------------------------------------
            // Packed little-endian float32 array. Stored as a binary blob rather
            // than JSON: 4 bytes per dimension instead of ~12, and unpack() is an
            // order of magnitude faster than json_decode() when scoring tens of
            // thousands of candidates. BLOB caps at 65,535 bytes = 16,383
            // dimensions, comfortably above any current embedding model (the
            // widest in common use is 4,096); MySQL's strict mode would reject
            // an oversized write rather than truncate it, so a future model that
            // exceeds this fails loudly and needs a MEDIUMBLOB migration.
            $table->binary('embedding')->nullable();
            $table->string('embedding_provider', 64)->nullable();
            $table->string('embedding_model')->nullable();
            $table->unsignedSmallInteger('embedding_dimensions')->nullable();
            $table->unsignedInteger('embedding_version')->default(1);
            $table->string('embedding_status', 32)->default('pending');
            $table->timestamp('embedded_at')->nullable();

            // Denormalised retrieval filters. Copied from the parent source so
            // the permission- and freshness-filtered candidate query touches one
            // table only — the hot path in every retrieval request.
            $table->string('source_type', 32);
            $table->string('priority', 32)->default('normal');
            $table->boolean('is_retrievable')->default(false);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();

            $table->timestamps();

            $table->index(['knowledge_base_id', 'is_retrievable', 'embedding_status'], 'knowledge_chunks_retrieval_index');
            $table->index(['knowledge_document_id', 'chunk_index'], 'kchunk_document_chunk_index_idx');
            $table->index(['knowledge_source_id', 'is_retrievable'], 'kchunk_source_is_retrievable_idx');
            $table->index(['knowledge_collection_id', 'is_retrievable'], 'kchunk_collection_is_retrievable_idx');
            $table->index('embedding_status', 'kchunk_embedding_status_idx');
            $table->index(['knowledge_base_id', 'content_hash'], 'knowledge_chunks_base_content_index');
            $table->unique(['owner_key', 'chunk_index'], 'knowledge_chunks_owner_index_unique');
        });

        // Keyword half of hybrid retrieval. MySQL InnoDB full-text; the
        // retrieval service falls back to LIKE scanning if this index is
        // unavailable (e.g. an installation on an engine without FTS support).
        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->fullText('content', 'knowledge_chunks_content_fulltext');
        });

        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->foreign('active_version_id')
                ->references('id')->on('knowledge_document_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });

        Schema::dropIfExists('knowledge_chunks');
        Schema::dropIfExists('knowledge_faq_versions');
        Schema::dropIfExists('knowledge_faqs');
        Schema::dropIfExists('knowledge_website_pages');
        Schema::dropIfExists('knowledge_document_versions');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('knowledge_source_credentials');
        Schema::dropIfExists('knowledge_sources');
    }
};
