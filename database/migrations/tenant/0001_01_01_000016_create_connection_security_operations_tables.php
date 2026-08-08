<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->string('http_method')->nullable()->after('status');
            $table->string('payload_hash')->nullable()->index()->after('payload');
            $table->unsignedInteger('payload_size')->default(0)->after('payload_hash');
            $table->unsignedSmallInteger('response_status')->nullable()->after('processed_at');
            $table->unsignedInteger('latency_ms')->nullable()->after('response_status');
            $table->timestamp('replayed_at')->nullable()->after('latency_ms');
            $table->foreignId('replayed_by')->nullable()->after('replayed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('oauth_authorization_states', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_integration_id')->constrained('connection_integrations')->cascadeOnDelete();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('state_hash')->unique();
            $table->text('code_verifier')->nullable();
            $table->string('code_challenge')->nullable();
            $table->json('scopes')->nullable();
            $table->string('redirect_path')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'connection_integration_id', 'expires_at'], 'oauth_state_tenant_integration_expiry');
        });

        Schema::create('credential_rotations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->foreignId('connection_credential_id')->nullable()->constrained('connection_credentials')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->string('reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_rate_limits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('bucket')->default('default')->index();
            $table->unsignedInteger('limit')->nullable();
            $table->unsignedInteger('remaining')->nullable();
            $table->timestamp('resets_at')->nullable()->index();
            $table->timestamp('backoff_until')->nullable()->index();
            $table->json('headers')->nullable();
            $table->timestamp('observed_at')->index();
            $table->timestamps();

            $table->unique(['connection_id', 'provider', 'bucket'], 'provider_rate_limit_unique');
        });

        Schema::create('connection_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->string('status')->index();
            $table->string('health_status')->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('message')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
        });

        Schema::create('sync_run_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('tenant_id')->index();
            $table->foreignId('sync_run_id')->constrained('sync_runs')->cascadeOnDelete();
            $table->foreignId('data_source_id')->nullable()->constrained('data_sources')->nullOnDelete();
            $table->string('external_id')->index();
            $table->string('operation')->index();
            $table->string('status')->index();
            $table->string('content_hash')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['sync_run_id', 'external_id', 'operation'], 'sync_run_item_idempotency');
        });

        Schema::create('connection_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('connection_id')->nullable()->constrained('connections')->cascadeOnDelete();
            $table->string('key_hash')->unique();
            $table->string('operation')->index();
            $table->string('status')->default('started')->index();
            $table->json('response')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('webhook_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('webhook_event_id')->constrained('webhook_events')->cascadeOnDelete();
            $table->unsignedInteger('attempt')->default(1);
            $table->string('status')->index();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('connection_idempotency_keys');
        Schema::dropIfExists('sync_run_items');
        Schema::dropIfExists('connection_health_checks');
        Schema::dropIfExists('provider_rate_limits');
        Schema::dropIfExists('credential_rotations');
        Schema::dropIfExists('oauth_authorization_states');

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('replayed_by');
            $table->dropColumn([
                'http_method',
                'payload_hash',
                'payload_size',
                'response_status',
                'latency_ms',
                'replayed_at',
            ]);
        });
    }
};
