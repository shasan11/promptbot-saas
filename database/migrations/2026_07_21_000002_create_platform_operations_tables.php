<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_operations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type')->index();
            $table->string('status')->default('queued')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->foreignUuid('requested_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->text('reason')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('failure_context')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->json('logs')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('system_health_checks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->index();
            $table->string('status')->default('unknown')->index();
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('backup_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('scope')->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('encrypted')->default(true);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_records');
        Schema::dropIfExists('system_health_checks');
        Schema::dropIfExists('platform_operations');
    }
};
