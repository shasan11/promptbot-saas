<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('provider', 48)->index();
            $table->boolean('enabled')->default(true)->index();
            $table->string('status', 32)->default('untested')->index();
            $table->string('default_chat_model')->nullable();
            $table->string('default_fast_model')->nullable();
            $table->string('default_reasoning_model')->nullable();
            $table->string('default_embedding_model')->nullable();
            $table->string('base_url', 2048)->nullable();
            $table->string('organization')->nullable();
            $table->longText('credentials_encrypted')->nullable();
            $table->json('configuration')->nullable();
            $table->json('capabilities')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_successful_test_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->string('last_error_code', 64)->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('circuit_open_until')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_prompts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('type', 48)->index();
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->longText('template');
            $table->json('variables')->nullable();
            $table->unsignedInteger('draft_version')->default(1);
            $table->unsignedBigInteger('active_version_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('prompt_id')->constrained('ai_prompts')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('template');
            $table->json('configuration')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['prompt_id', 'version']);
        });

        Schema::table('ai_prompts', function (Blueprint $table): void {
            $table->foreign('active_version_id')->references('id')->on('ai_prompt_versions')->nullOnDelete();
        });

        Schema::create('ai_agents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('agent_key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->string('purpose')->nullable();
            $table->longText('system_instructions')->nullable();
            $table->string('tone', 48)->default('professional_friendly');
            $table->string('language_mode', 32)->default('match_customer');
            $table->json('supported_languages')->nullable();
            $table->foreignId('provider_config_id')->nullable()->constrained('ai_provider_configs')->nullOnDelete();
            $table->string('model')->nullable();
            $table->json('model_parameters')->nullable();
            $table->string('deployment_mode', 24)->default('copilot');
            $table->json('confidence_policy')->nullable();
            $table->boolean('memory_enabled')->default(false);
            $table->string('memory_strategy', 32)->default('recent_with_summary');
            $table->json('memory_config')->nullable();
            $table->unsignedInteger('max_context_tokens')->nullable();
            $table->unsignedSmallInteger('max_tool_calls')->default(3);
            $table->unsignedSmallInteger('max_steps')->default(8);
            $table->unsignedSmallInteger('timeout_seconds')->default(45);
            $table->boolean('require_citations')->default(true);
            $table->string('fallback_behavior', 32)->default('human_handoff');
            $table->string('human_approval_mode', 32)->default('risk_based');
            $table->boolean('auto_reply_enabled')->default(false);
            $table->json('behaviors')->nullable();
            $table->json('guardrails')->nullable();
            $table->json('limits')->nullable();
            $table->unsignedInteger('draft_version')->default(1);
            $table->unsignedBigInteger('deployed_version_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deployed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('ai_agent_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('configuration_snapshot');
            $table->json('prompt_snapshot')->nullable();
            $table->json('knowledge_snapshot')->nullable();
            $table->json('tool_policy_snapshot')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['agent_id', 'version']);
        });

        Schema::table('ai_agents', function (Blueprint $table): void {
            $table->foreign('deployed_version_id')->references('id')->on('ai_agent_versions')->nullOnDelete();
        });

        Schema::create('ai_agent_tools', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->foreignId('connection_action_id')->constrained('connection_actions')->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->string('approval_policy', 24)->default('inherit');
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'connection_action_id']);
        });

        Schema::create('ai_agent_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_id')->constrained('ai_agents')->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->string('deployment_mode', 24)->default('copilot');
            $table->boolean('enabled')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->unique(['agent_id', 'channel_id']);
        });

        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('agent_version_id')->nullable()->constrained('ai_agent_versions')->nullOnDelete();
            $table->string('feature', 64)->index();
            $table->string('operation', 64)->index();
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('provider_config_id')->nullable()->constrained('ai_provider_configs')->nullOnDelete();
            $table->string('provider', 48)->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_key')->nullable();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedBigInteger('input_token_count')->nullable();
            $table->unsignedBigInteger('output_token_count')->nullable();
            $table->unsignedBigInteger('cached_token_count')->nullable();
            $table->unsignedBigInteger('reasoning_token_count')->nullable();
            $table->unsignedBigInteger('total_token_count')->nullable();
            $table->decimal('estimated_cost', 14, 8)->nullable();
            $table->char('currency', 3)->nullable();
            $table->uuid('retrieval_log_uuid')->nullable()->index();
            $table->unsignedSmallInteger('tool_call_count')->default(0);
            $table->unsignedSmallInteger('approval_count')->default(0);
            $table->string('error_category', 48)->nullable()->index();
            $table->string('error_code', 96)->nullable();
            $table->text('error_message_safe')->nullable();
            $table->uuid('trace_id')->nullable()->index();
            $table->uuid('correlation_id')->nullable()->index();
            $table->string('idempotency_key_hash', 64)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id']);
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('feature', 64)->index();
            $table->string('provider', 48)->nullable()->index();
            $table->string('model')->nullable()->index();
            $table->unsignedBigInteger('input_tokens')->nullable();
            $table->unsignedBigInteger('output_tokens')->nullable();
            $table->unsignedBigInteger('cached_tokens')->nullable();
            $table->unsignedBigInteger('reasoning_tokens')->nullable();
            $table->decimal('estimated_cost', 14, 8)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('occurred_at')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->string('type', 48)->index();
            $table->longText('text')->nullable();
            $table->json('structured_payload')->nullable();
            $table->json('citations')->nullable();
            $table->json('evidence')->nullable();
            $table->decimal('model_reported_confidence', 5, 4)->nullable();
            $table->string('decision_confidence', 16)->nullable();
            $table->json('confidence_basis')->nullable();
            $table->string('status', 24)->default('generated')->index();
            $table->string('provider', 48)->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_key')->nullable();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('ai_conversation_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->unique()->constrained('conversations')->cascadeOnDelete();
            $table->longText('summary')->nullable();
            $table->string('intent', 48)->nullable()->index();
            $table->string('sentiment', 24)->nullable()->index();
            $table->string('urgency', 16)->nullable()->index();
            $table->string('language', 16)->nullable();
            $table->string('suggested_priority', 16)->nullable();
            $table->foreignId('suggested_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->json('suggested_tags')->nullable();
            $table->json('risk_flags')->nullable();
            $table->text('customer_goal')->nullable();
            $table->foreignId('last_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->foreignId('summary_until_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->timestamp('summary_generated_at')->nullable();
            $table->timestamp('classified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->unsignedBigInteger('tool_call_id')->nullable()->index();
            $table->foreignId('connection_action_id')->nullable()->constrained('connection_actions')->nullOnDelete();
            $table->string('resource_type')->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->string('risk_level', 16)->index();
            $table->string('approval_type', 32)->default('tool_execution');
            $table->string('requested_action');
            $table->json('arguments_redacted')->nullable();
            $table->json('context')->nullable();
            $table->longText('resume_token_encrypted')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('requested_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('connection_action_id')->nullable()->constrained('connection_actions')->nullOnDelete();
            $table->string('tool_call_id')->nullable()->index();
            $table->string('tool_key')->index();
            $table->string('risk_level', 16)->index();
            $table->json('arguments_redacted')->nullable();
            $table->string('argument_hash', 64)->index();
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approval_request_id')->nullable()->constrained('ai_approval_requests')->nullOnDelete();
            $table->string('idempotency_key_hash', 64)->unique();
            $table->string('status', 32)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('result_excerpt')->nullable();
            $table->string('error_code', 96)->nullable();
            $table->text('error_message_safe')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->foreignId('ai_suggestion_id')->nullable()->constrained('ai_suggestions')->nullOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rating', 32)->index();
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_evaluation_suites', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_evaluation_cases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('suite_id')->constrained('ai_evaluation_suites')->cascadeOnDelete();
            $table->string('category', 48)->index();
            $table->string('name');
            $table->longText('input');
            $table->json('expected')->nullable();
            $table->json('assertions');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_evaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('suite_id')->constrained('ai_evaluation_suites')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('ai_agents')->nullOnDelete();
            $table->foreignId('agent_version_id')->nullable()->constrained('ai_agent_versions')->nullOnDelete();
            $table->string('status', 24)->default('queued')->index();
            $table->unsignedInteger('total_cases')->default(0);
            $table->unsignedInteger('passed_cases')->default(0);
            $table->unsignedInteger('failed_cases')->default(0);
            $table->decimal('pass_rate', 6, 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_evaluation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_run_id')->constrained('ai_evaluation_runs')->cascadeOnDelete();
            $table->foreignId('evaluation_case_id')->constrained('ai_evaluation_cases')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->string('status', 24)->index();
            $table->json('assertion_results')->nullable();
            $table->longText('output_excerpt')->nullable();
            $table->text('error_message_safe')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();
            $table->unique(['evaluation_run_id', 'evaluation_case_id'], 'ai_eval_run_case_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_results');
        Schema::dropIfExists('ai_evaluation_runs');
        Schema::dropIfExists('ai_evaluation_cases');
        Schema::dropIfExists('ai_evaluation_suites');
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_approval_requests');
        Schema::dropIfExists('ai_conversation_insights');
        Schema::dropIfExists('ai_suggestions');
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('ai_agent_channels');
        Schema::dropIfExists('ai_agent_tools');

        Schema::table('ai_agents', function (Blueprint $table): void {
            $table->dropForeign(['deployed_version_id']);
        });
        Schema::dropIfExists('ai_agent_versions');
        Schema::dropIfExists('ai_agents');

        Schema::table('ai_prompts', function (Blueprint $table): void {
            $table->dropForeign(['active_version_id']);
        });
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_provider_configs');
    }
};
