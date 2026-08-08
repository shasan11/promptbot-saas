<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge Base module — operations, observability and analytics.
 *
 * These tables answer the questions an operator asks when something is wrong
 * ("what is running / what broke / why") and the questions a knowledge owner
 * asks when nothing is wrong ("what are people asking, and are we answering").
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * One row per unit of asynchronous work, created at dispatch time so the
         * Processing Queue page can show "queued" before a worker ever picks the
         * job up. Distinct from Laravel's `jobs` table, which is an internal
         * transport detail and is emptied as soon as work completes.
         */
        Schema::create('knowledge_processing_jobs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->cascadeOnDelete();

            $table->string('job_type', 64);
            $table->string('queue', 64)->nullable();
            $table->string('status', 32)->default('queued');
            $table->string('current_stage', 32)->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('items_total')->default(0);
            $table->unsignedInteger('items_processed')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->unsignedInteger('max_attempts')->default(3);

            // Set on dispatch and checked cooperatively by long-running workers
            // (crawls, bulk re-index) so "Cancel" stops work rather than merely
            // hiding the row.
            $table->boolean('cancel_requested')->default(false);

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('last_error')->nullable();
            // Ties every log line, failure and structured log entry for one
            // logical operation together across retries and child jobs.
            $table->uuid('correlation_id')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'queued_at'], 'kjob_status_queued_at_idx');
            $table->index(['knowledge_base_id', 'status'], 'kjob_base_status_idx');
            $table->index('correlation_id', 'kjob_correlation_idx');
        });

        Schema::create('knowledge_processing_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_processing_job_id')->nullable()->constrained('knowledge_processing_jobs')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->cascadeOnDelete();

            $table->string('stage', 32);
            $table->string('level', 16)->default('info');
            $table->string('message', 1024);
            // Metrics and counters only. Never raw document content — these logs
            // are readable by anyone with knowledge.sources.view.
            $table->json('context')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['knowledge_document_id', 'created_at'], 'kplog_document_created_at_idx');
            $table->index(['knowledge_source_id', 'created_at'], 'kplog_source_created_at_idx');
            $table->index(['stage', 'level'], 'kplog_stage_level_idx');
        });

        /**
         * The Failed Sources page reads this table. One row per failure event —
         * history is kept rather than overwritten so a source that fails
         * intermittently is distinguishable from one that has failed once.
         */
        Schema::create('knowledge_failures', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('knowledge_sources')->cascadeOnDelete();
            $table->foreignId('knowledge_document_id')->nullable()->constrained('knowledge_documents')->cascadeOnDelete();
            $table->foreignId('knowledge_processing_job_id')->nullable()->constrained('knowledge_processing_jobs')->nullOnDelete();

            $table->string('stage', 32);
            $table->string('category', 48);
            // Split intentionally: `message` is safe to render to any operator,
            // `technical_details` holds the exception class/trace and is gated
            // behind knowledge.manage.
            $table->text('message');
            $table->longText('technical_details')->nullable();
            $table->unsignedInteger('attempt')->default(1);
            $table->boolean('retryable')->default(true);
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'created_at'], 'kfail_category_created_at_idx');
            $table->index(['knowledge_source_id', 'resolved_at'], 'kfail_source_resolved_at_idx');
            $table->index(['knowledge_base_id', 'resolved_at'], 'kfail_base_resolved_at_idx');
        });

        Schema::create('knowledge_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->string('trigger', 32)->default('scheduled'); // scheduled | manual | api
            $table->string('status', 32)->default('queued');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->unsignedInteger('items_discovered')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_unchanged')->default(0);
            $table->unsignedInteger('items_deleted')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);

            $table->text('last_error')->nullable();
            $table->json('summary')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['knowledge_source_id', 'created_at'], 'ksync_source_created_at_idx');
            $table->index('status', 'ksync_status_idx');
        });

        /**
         * One row per retrieval request, whether it came from the playground, an
         * AI agent, or the API. This is the substrate for every analytics
         * number, so it is written on the hot path — keep it to a single insert.
         */
        Schema::create('knowledge_retrieval_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();

            $table->string('channel', 32)->default('playground'); // playground | agent | api | inbox
            $table->string('actor_type', 32)->nullable();          // user | agent | system
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_key')->nullable();

            $table->text('query');
            // Normalised + hashed so "What is the refund window?" and "what is
            // the refund window" aggregate into one row on the Top Queries and
            // Zero-result reports.
            $table->char('query_hash', 64);
            $table->string('language', 12)->nullable();
            $table->string('retrieval_mode', 32)->nullable();

            $table->unsignedSmallInteger('candidates_semantic')->default(0);
            $table->unsignedSmallInteger('candidates_keyword')->default(0);
            $table->unsignedSmallInteger('results_returned')->default(0);
            $table->decimal('top_score', 6, 5)->nullable();
            $table->decimal('average_score', 6, 5)->nullable();
            $table->boolean('zero_results')->default(false);
            $table->boolean('below_threshold')->default(false);

            $table->unsignedInteger('embedding_ms')->nullable();
            $table->unsignedInteger('semantic_ms')->nullable();
            $table->unsignedInteger('keyword_ms')->nullable();
            $table->unsignedInteger('rerank_ms')->nullable();
            $table->unsignedInteger('total_ms')->nullable();

            $table->json('filters')->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['knowledge_base_id', 'created_at'], 'krlog_base_created_at_idx');
            $table->index(['zero_results', 'created_at'], 'krlog_zero_results_created_at_idx');
            $table->index(['query_hash', 'created_at'], 'krlog_query_hash_created_at_idx');
            $table->index(['channel', 'created_at'], 'krlog_channel_created_at_idx');
        });

        Schema::create('knowledge_retrieval_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_retrieval_log_id')->constrained('knowledge_retrieval_logs')->cascadeOnDelete();
            $table->foreignId('knowledge_chunk_id')->nullable()->constrained('knowledge_chunks')->nullOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->decimal('semantic_score', 6, 5)->nullable();
            $table->decimal('keyword_score', 6, 5)->nullable();
            $table->decimal('final_score', 6, 5)->nullable();
            $table->boolean('included_in_context')->default(true);
            $table->string('exclusion_reason', 64)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['knowledge_retrieval_log_id', 'rank'], 'knowledge_retrieval_results_rank_index');
            $table->index('knowledge_chunk_id', 'krres_chunk_idx');
        });

        /**
         * Questions the knowledge base could not answer well, rolled up by
         * normalised query. Populated from zero-result and below-threshold
         * retrievals, and actionable straight into a new FAQ.
         */
        Schema::create('knowledge_gaps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->nullOnDelete();
            $table->text('question');
            $table->char('query_hash', 64);
            $table->string('origin', 32)->default('zero_result'); // zero_result | low_confidence | escalation
            $table->unsignedInteger('occurrences')->default(1);
            $table->decimal('best_score', 6, 5)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 32)->default('open'); // open | assigned | resolved | ignored
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_faq_id')->nullable()->constrained('knowledge_faqs')->nullOnDelete();
            $table->timestamps();

            // knowledge_base_id is nullable (a retrieval may span several bases),
            // and MySQL treats NULLs in a unique index as distinct — which would
            // let the same unanswered question accumulate one row per occurrence
            // instead of incrementing a counter. Hash the scope explicitly.
            $table->char('dedupe_key', 64)->unique();

            $table->index(['status', 'occurrences'], 'kgap_status_occurrences_idx');
        });

        /**
         * Daily cost/usage rollup. Written incrementally by the embedding and
         * OCR services rather than derived at read time, so the analytics page
         * never has to scan chunk history.
         */
        Schema::create('knowledge_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->date('usage_date');
            $table->foreignId('knowledge_base_id')->nullable()->constrained('knowledge_bases')->cascadeOnDelete();
            $table->foreignId('knowledge_source_id')->nullable()->constrained('knowledge_sources')->nullOnDelete();
            $table->string('provider', 64);
            $table->string('operation', 48); // embedding | ocr | rerank | generation
            $table->unsignedBigInteger('units')->default(0);      // tokens, pages, calls
            $table->unsignedInteger('request_count')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->timestamps();

            // Same NULL-distinctness problem as knowledge_gaps: both foreign keys
            // are optional, so the daily rollup is keyed on a hash of the scope
            // tuple rather than on the columns directly.
            $table->char('dedupe_key', 64)->unique();

            $table->index(['usage_date', 'operation'], 'kusage_usage_date_operation_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_usage_records');
        Schema::dropIfExists('knowledge_gaps');
        Schema::dropIfExists('knowledge_retrieval_results');
        Schema::dropIfExists('knowledge_retrieval_logs');
        Schema::dropIfExists('knowledge_sync_runs');
        Schema::dropIfExists('knowledge_failures');
        Schema::dropIfExists('knowledge_processing_logs');
        Schema::dropIfExists('knowledge_processing_jobs');
    }
};
