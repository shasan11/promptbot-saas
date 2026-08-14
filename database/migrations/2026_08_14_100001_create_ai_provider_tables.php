<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('driver', 40)->index();
            $table->string('name', 150);
            $table->string('slug', 150)->unique();
            $table->string('base_url', 500)->nullable();
            $table->text('api_key')->nullable();
            $table->string('organization_id')->nullable();
            $table->text('extra_headers')->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('priority')->default(100);
            $table->unsignedSmallInteger('timeout_seconds')->nullable();
            $table->unsignedTinyInteger('max_retries')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_models', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_provider_id')->constrained('ai_providers')->cascadeOnDelete();
            $table->string('model_key', 150);
            $table->string('display_name', 150);
            $table->string('capability', 20)->index();
            $table->unsignedInteger('context_window')->nullable();
            $table->unsignedInteger('max_output_tokens')->nullable();
            $table->unsignedInteger('embedding_dimensions')->nullable();
            $table->decimal('input_cost_per_million_tokens', 12, 6)->default(0);
            $table->decimal('output_cost_per_million_tokens', 12, 6)->default(0);
            $table->boolean('supports_streaming')->default(true);
            $table->boolean('supports_json_mode')->default(false);
            $table->boolean('is_enabled')->default(true)->index();
            $table->boolean('is_default_for_capability')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['ai_provider_id', 'model_key']);
        });

        Schema::create('ai_model_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('purpose', 60);
            $table->foreignUuid('ai_model_id')->constrained('ai_models')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['purpose', 'ai_model_id']);
            $table->index(['purpose', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_assignments');
        Schema::dropIfExists('ai_models');
        Schema::dropIfExists('ai_providers');
    }
};
