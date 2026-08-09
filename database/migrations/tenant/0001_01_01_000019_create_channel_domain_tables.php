<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique();
            $table->string('type', 32)->index(); $table->string('name'); $table->string('status', 32)->default('draft')->index();
            $table->json('configuration')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('business_hours_policy_id')->nullable()->constrained('business_hour_policies')->nullOnDelete();
            $table->boolean('auto_reply_enabled')->default(false); $table->text('signature')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_activity_at')->nullable()->index(); $table->timestamps(); $table->softDeletes();
            $table->index(['type', 'status', 'created_at']);
        });

        Schema::create('channel_credentials', function (Blueprint $table): void {
            $table->id(); $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 64); $table->text('encrypted_payload'); $table->string('status', 32)->default('active');
            $table->timestamp('last_rotated_at')->nullable(); $table->foreignId('rotated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('email_channel_settings', function (Blueprint $table): void {
            $table->id(); $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('inbox_address')->index(); $table->string('incoming_provider', 32)->default('webhook');
            $table->string('outgoing_provider', 32)->default('laravel_mail'); $table->string('from_name')->nullable();
            $table->string('reply_to_address')->nullable(); $table->boolean('capture_cc')->default(true); $table->boolean('capture_bcc')->default(false);
            $table->boolean('strip_quoted_replies')->default(true); $table->timestamps();
        });

        Schema::create('web_chat_widgets', function (Blueprint $table): void {
            $table->id(); $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_key', 64)->unique(); $table->string('widget_name'); $table->string('primary_color', 16)->default('#2563eb');
            $table->string('launcher_position', 16)->default('right'); $table->string('welcome_message')->default('How can we help?');
            $table->string('offline_message')->default('We are currently offline. Leave a message and we will get back to you.');
            $table->json('pre_chat_fields')->nullable(); $table->json('supported_languages')->nullable(); $table->json('allowed_origins')->nullable();
            $table->string('privacy_url')->nullable(); $table->string('terms_url')->nullable(); $table->boolean('allow_attachments')->default(true);
            $table->boolean('require_email')->default(true); $table->boolean('require_name')->default(true); $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('channel_delivery_logs', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique(); $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16); $table->string('status', 32)->index(); $table->string('provider_message_id')->nullable()->index();
            $table->string('idempotency_key')->nullable(); $table->string('recipient')->nullable(); $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); $table->timestamp('attempted_at')->useCurrent(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->unique(['channel_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_delivery_logs'); Schema::dropIfExists('web_chat_widgets'); Schema::dropIfExists('email_channel_settings'); Schema::dropIfExists('channel_credentials'); Schema::dropIfExists('channels');
    }
};
