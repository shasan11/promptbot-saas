<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_actions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_integration_id')->nullable()->constrained('connection_integrations')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->cascadeOnDelete();
            $table->string('key')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('action_type')->default('provider_action')->index();
            $table->string('risk_level')->default('low')->index();
            $table->boolean('requires_approval')->default(false);
            $table->boolean('enabled_for_ai')->default(false);
            $table->boolean('enabled_for_workflows')->default(false);
            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('configuration')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['connection_id', 'key'], 'connection_action_unique');
            $table->index(['tenant_id', 'risk_level', 'status']);
        });

        Schema::create('connection_triggers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_integration_id')->nullable()->constrained('connection_integrations')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->cascadeOnDelete();
            $table->string('key')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type')->default('provider_event')->index();
            $table->json('event_schema')->nullable();
            $table->json('configuration')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['connection_id', 'key'], 'connection_trigger_unique');
        });

        Schema::create('connection_action_executions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignId('connection_action_id')->nullable()->constrained('connection_actions')->nullOnDelete();
            $table->string('actor_type')->nullable()->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('agent_key')->nullable()->index();
            $table->string('workflow_key')->nullable()->index();
            $table->string('status')->default('queued')->index();
            $table->string('risk_level')->default('low')->index();
            $table->boolean('approval_required')->default(false);
            $table->string('idempotency_key_hash')->nullable()->unique();
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
        });

        Schema::create('api_operations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('method')->default('GET');
            $table->string('path');
            $table->json('headers')->nullable();
            $table->json('query_schema')->nullable();
            $table->json('body_schema')->nullable();
            $table->string('risk_level')->default('low')->index();
            $table->boolean('enabled_for_ai')->default(false);
            $table->boolean('enabled_for_workflows')->default(false);
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->unsignedInteger('max_response_kb')->default(512);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['connection_id', 'key']);
        });

        Schema::create('database_data_source_configs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('data_source_id')->constrained('data_sources')->cascadeOnDelete();
            $table->string('schema_name')->nullable();
            $table->string('table_name');
            $table->string('primary_key')->nullable();
            $table->string('incremental_column')->nullable();
            $table->json('allowed_columns')->nullable();
            $table->json('excluded_columns')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_limit')->default(10000);
            $table->boolean('read_only')->default(true);
            $table->text('raw_sql')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('connection_usage_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('connection_action_execution_id')->nullable()->constrained('connection_action_executions')->nullOnDelete();
            $table->string('usage_type')->index();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->string('unit')->default('count');
            $table->unsignedBigInteger('bytes')->default(0);
            $table->json('metadata')->nullable();
            $table->date('usage_date')->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'usage_type', 'usage_date']);
        });

        Schema::create('connection_agent_access', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('agent_key')->index();
            $table->json('allowed_actions')->nullable();
            $table->json('allowed_resources')->nullable();
            $table->boolean('read_only')->default(true);
            $table->boolean('approval_required')->default(false);
            $table->unsignedInteger('rate_limit_per_hour')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'agent_key']);
        });

        Schema::create('connection_workflow_access', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('workflow_key')->index();
            $table->json('allowed_actions')->nullable();
            $table->json('allowed_triggers')->nullable();
            $table->boolean('approval_required')->default(false);
            $table->timestamps();

            $table->unique(['connection_id', 'workflow_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_workflow_access');
        Schema::dropIfExists('connection_agent_access');
        Schema::dropIfExists('connection_usage_records');
        Schema::dropIfExists('database_data_source_configs');
        Schema::dropIfExists('api_operations');
        Schema::dropIfExists('connection_action_executions');
        Schema::dropIfExists('connection_triggers');
        Schema::dropIfExists('connection_actions');
    }
};
