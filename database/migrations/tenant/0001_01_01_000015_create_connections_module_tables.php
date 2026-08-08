<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('provider')->index();
            $table->string('category')->index();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->json('auth_methods');
            $table->json('capabilities');
            $table->json('resource_types')->nullable();
            $table->json('action_definitions')->nullable();
            $table->json('trigger_definitions')->nullable();
            $table->json('configuration_schema')->nullable();
            $table->json('credential_schema')->nullable();
            $table->string('connector_class')->nullable();
            $table->string('connector_version')->default('v1');
            $table->string('status')->default('available')->index();
            $table->boolean('is_beta')->default(false);
            $table->timestamps();
        });

        Schema::create('connections', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_integration_id')->constrained('connection_integrations')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('health_status')->default('unknown')->index();
            $table->string('connection_type')->default('application')->index();
            $table->string('auth_type')->default('none')->index();
            $table->string('environment')->default('production')->index();
            $table->string('provider_account_name')->nullable();
            $table->string('provider_account_id')->nullable();
            $table->json('usage')->nullable();
            $table->json('configuration')->nullable();
            $table->string('credential_status')->default('missing')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('technical_contact')->nullable();
            $table->string('business_contact')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->timestamp('last_successful_check_at')->nullable();
            $table->timestamp('last_error_at')->nullable()->index();
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'health_status']);
            $table->index(['connection_integration_id', 'status']);
        });

        Schema::create('connection_credentials', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('active')->index();
            $table->text('encrypted_payload')->nullable();
            $table->string('masked_secret')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'connection_id', 'status']);
        });

        Schema::create('connection_resources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->string('resource_type')->index();
            $table->string('parent_external_id')->nullable()->index();
            $table->string('path')->nullable();
            $table->json('metadata')->nullable();
            $table->json('capabilities')->nullable();
            $table->string('data_classification')->default('internal')->index();
            $table->string('status')->default('available')->index();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'external_id']);
            $table->index(['tenant_id', 'connection_id', 'resource_type']);
        });

        Schema::create('connection_resource_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_resource_id')->constrained('connection_resources')->cascadeOnDelete();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('capabilities')->nullable();
            $table->timestamps();

            $table->unique(['connection_resource_id', 'subject_type', 'subject_id'], 'resource_subject_unique');
        });

        Schema::create('data_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignId('connection_resource_id')->nullable()->constrained('connection_resources')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('resource_type')->index();
            $table->json('usage')->nullable();
            $table->json('configuration')->nullable();
            $table->string('status')->default('active')->index();
            $table->string('sync_mode')->default('manual')->index();
            $table->string('sync_schedule')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable()->index();
            $table->timestamp('last_successful_sync_at')->nullable();
            $table->timestamp('next_sync_at')->nullable()->index();
            $table->text('last_cursor')->nullable();
            $table->unsignedBigInteger('records_synced')->default(0);
            $table->unsignedBigInteger('bytes_synced')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'connection_id', 'status']);
            $table->index(['tenant_id', 'sync_mode', 'next_sync_at']);
        });

        Schema::create('sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->string('sync_type')->default('manual')->index();
            $table->string('status')->default('queued')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('cursor_before')->nullable();
            $table->text('cursor_after')->nullable();
            $table->unsignedInteger('items_discovered')->default(0);
            $table->unsignedInteger('items_created')->default(0);
            $table->unsignedInteger('items_updated')->default(0);
            $table->unsignedInteger('items_deleted')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->unsignedInteger('items_failed')->default(0);
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->unsignedInteger('api_requests')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_summary')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger_source')->default('manual');
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['connection_id', 'data_source_id', 'status']);
        });

        Schema::create('connection_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('sync_runs')->nullOnDelete();
            $table->string('level')->default('info')->index();
            $table->string('event')->index();
            $table->string('status')->nullable()->index();
            $table->text('message')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('context')->nullable();
            $table->string('error_code')->nullable();
            $table->string('correlation_id')->nullable()->index();
            $table->string('request_id')->nullable()->index();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'connection_id', 'created_at']);
            $table->index(['tenant_id', 'event', 'created_at']);
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('name');
            $table->string('provider')->index();
            $table->string('status')->default('active')->index();
            $table->string('endpoint_path')->unique();
            $table->string('signature_algorithm')->default('hmac_sha256');
            $table->text('encrypted_secret')->nullable();
            $table->json('event_types')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('webhook_endpoint_id')->nullable()->constrained('webhook_endpoints')->nullOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('provider_event_id')->nullable();
            $table->string('event_type')->nullable()->index();
            $table->string('status')->default('received')->index();
            $table->json('headers')->nullable();
            $table->json('payload')->nullable();
            $table->string('signature')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['webhook_endpoint_id', 'provider_event_id'], 'webhook_event_dedupe');
            $table->index(['tenant_id', 'status', 'received_at']);
        });

        Schema::create('connection_access_grants', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('capabilities')->nullable();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'subject_type', 'subject_id'], 'connection_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_access_grants');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('connection_logs');
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('data_sources');
        Schema::dropIfExists('connection_resource_permissions');
        Schema::dropIfExists('connection_resources');
        Schema::dropIfExists('connection_credentials');
        Schema::dropIfExists('connections');
        Schema::dropIfExists('connection_integrations');
    }
};
