<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable()->index();
            $table->uuid('ai_provider_id')->nullable();
            $table->string('provider_driver', 40)->nullable();
            $table->string('provider_name', 150)->nullable();
            $table->uuid('ai_model_id')->nullable();
            $table->string('model_key', 150)->nullable();
            $table->string('purpose', 60)->index();
            $table->string('capability', 20);
            $table->string('status', 20)->index();
            $table->string('error_code', 60)->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->uuid('request_uuid')->nullable()->index();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['ai_provider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
