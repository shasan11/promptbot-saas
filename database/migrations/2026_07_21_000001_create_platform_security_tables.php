<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->foreignUuid('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->foreignUuid('invited_by')->constrained('central_users')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_admin_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('administrator_id')->constrained('central_users')->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_impersonation_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('administrator_id')->constrained('central_users')->cascadeOnDelete();
            $table->string('tenant_id')->index();
            $table->string('impersonated_user_email')->nullable();
            $table->text('reason');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_impersonation_sessions');
        Schema::dropIfExists('platform_admin_sessions');
        Schema::dropIfExists('platform_admin_invitations');
    }
};
