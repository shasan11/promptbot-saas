<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Articles: long-form authored content with an editorial review
 * step before it can be retrieved. Kept as its own table rather than folded
 * into `knowledge_documents` (the way manual text is) because the review
 * lifecycle — reviewer, review note, submitted/reviewed/published timestamps
 * — has no equivalent in the document processing pipeline and would not fit
 * `DocumentStatus` without conflating "extraction finished" with "a human
 * approved this."
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_collection_id')->nullable()->constrained('knowledge_collections')->nullOnDelete();

            $table->string('title');
            $table->string('slug');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->string('language', 12)->default('en');
            $table->string('status')->default('draft');
            $table->boolean('allow_ai_access')->default(true);
            $table->char('content_hash', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('indexed_at')->nullable();

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('review_requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['knowledge_base_id', 'slug'], 'karticle_base_slug_uniq');
            $table->index(['knowledge_base_id', 'status'], 'karticle_base_status_idx');
            $table->index('content_hash', 'karticle_content_hash_idx');
        });

        Schema::create('knowledge_article_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_article_id')->constrained('knowledge_articles')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('body');
            $table->string('status')->nullable();
            $table->string('change_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['knowledge_article_id', 'version_number'], 'karticlev_article_version_number_uniq');
        });

        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->foreignId('knowledge_article_id')->nullable()
                ->after('knowledge_faq_id')
                ->constrained('knowledge_articles')->cascadeOnDelete();

            $table->index(['knowledge_article_id', 'chunk_index'], 'kchunk_article_chunk_index_idx');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_chunks', function (Blueprint $table): void {
            $table->dropIndex('kchunk_article_chunk_index_idx');
            $table->dropConstrainedForeignId('knowledge_article_id');
        });

        Schema::dropIfExists('knowledge_article_versions');
        Schema::dropIfExists('knowledge_articles');
    }
};
