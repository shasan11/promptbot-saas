<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete(); $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('channel_id')->constrained()->restrictOnDelete(); $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('open')->index(); $table->string('priority', 16)->default('normal')->index(); $table->string('subject')->nullable();
            $table->timestamp('first_message_at')->nullable(); $table->timestamp('last_message_at')->nullable()->index(); $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable(); $table->timestamp('closed_at')->nullable(); $table->timestamp('snoozed_until')->nullable()->index();
            $table->unsignedInteger('message_count')->default(0); $table->unsignedInteger('unread_count')->default(0);
            $table->string('external_reference')->nullable()->index(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->index(['status', 'assignee_id', 'last_message_at']); $table->index(['team_id', 'status', 'last_message_at']); $table->index(['channel_id', 'status', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique(); $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 32); $table->unsignedBigInteger('sender_id')->nullable(); $table->string('sender_name')->nullable();
            $table->string('direction', 16)->index(); $table->string('message_type', 16)->default('text'); $table->longText('body')->nullable(); $table->longText('body_html')->nullable();
            $table->string('channel_message_id')->nullable(); $table->foreignId('reply_to_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('status', 32)->default('received')->index(); $table->timestamp('sent_at')->nullable(); $table->timestamp('delivered_at')->nullable(); $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable(); $table->text('failure_reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamps();
            $table->unique(['conversation_id', 'channel_message_id']); $table->index(['conversation_id', 'created_at']); $table->index(['sender_type', 'sender_id']);
        });

        Schema::create('conversation_attachments', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique(); $table->foreignId('message_id')->constrained('messages')->cascadeOnDelete();
            $table->string('original_filename'); $table->string('stored_filename'); $table->string('mime_type', 127); $table->unsignedBigInteger('file_size');
            $table->string('storage_disk')->default('local'); $table->string('storage_path'); $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('checksum', 64); $table->timestamps();
        });

        Schema::create('conversation_assignments', function (Blueprint $table): void {
            $table->id(); $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete(); $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete(); $table->string('reason')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('conversation_followers', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->timestamp('created_at')->useCurrent();
            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('message_mentions', function (Blueprint $table): void {
            $table->id(); $table->foreignId('message_id')->constrained()->cascadeOnDelete(); $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete(); $table->timestamp('read_at')->nullable(); $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'read_at']); $table->index(['team_id', 'read_at']);
        });

        Schema::create('saved_views', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique(); $table->string('resource_type', 32); $table->string('name'); $table->json('filters');
            $table->string('scope', 16)->default('private'); $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('active')->default(true); $table->timestamps(); $table->index(['resource_type', 'scope', 'active']);
        });

        Schema::create('web_chat_visitors', function (Blueprint $table): void {
            $table->id(); $table->uuid('public_uuid')->unique(); $table->foreignId('web_chat_widget_id')->constrained()->cascadeOnDelete(); $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('session_token_hash', 64)->unique(); $table->string('locale', 12)->nullable(); $table->string('ip_hash', 64)->nullable(); $table->json('metadata')->nullable();
            $table->timestamp('last_seen_at')->nullable(); $table->timestamp('expires_at')->index(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_chat_visitors'); Schema::dropIfExists('saved_views'); Schema::dropIfExists('message_mentions'); Schema::dropIfExists('conversation_followers'); Schema::dropIfExists('conversation_assignments'); Schema::dropIfExists('conversation_attachments'); Schema::dropIfExists('messages'); Schema::dropIfExists('conversations');
    }
};
