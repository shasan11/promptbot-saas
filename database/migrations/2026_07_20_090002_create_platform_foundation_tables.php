<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admin_login_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('administrator_id')->nullable()->constrained('central_users')->nullOnDelete();
            $table->string('email');
            $table->boolean('successful')->default(false);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('attempted_at')->index();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('group')->default('general')->index();
            $table->string('key');
            $table->json('value')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('administrator_id')->nullable()->constrained('central_users')->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('action')->index();
            $table->string('entity_type')->nullable()->index();
            $table->string('entity_id')->nullable()->index();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->uuid('request_uuid')->nullable()->index();
            $table->uuid('impersonation_session_id')->nullable();
            $table->string('severity')->default('info')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('platform_admin_login_attempts');
    }
};
