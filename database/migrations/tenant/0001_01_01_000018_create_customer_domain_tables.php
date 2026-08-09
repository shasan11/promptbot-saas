<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('domain')->nullable()->index();
            $table->string('industry')->nullable()->index();
            $table->string('website')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('postal_code', 32)->nullable();
            $table->foreignId('account_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['name', 'status']);
        });

        Schema::create('contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('display_name');
            $table->string('email')->nullable()->index();
            $table->string('secondary_email')->nullable();
            $table->string('phone', 50)->nullable()->index();
            $table->string('secondary_phone', 50)->nullable();
            $table->string('country', 2)->nullable()->index();
            $table->string('timezone', 64)->nullable();
            $table->string('preferred_language', 12)->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->string('source', 64)->default('manual')->index();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable()->index();
            $table->timestamp('last_contacted_at')->nullable()->index();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['display_name', 'status']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('contact_points', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('label', 64)->nullable();
            $table->string('value');
            $table->string('normalized_value')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['contact_id', 'type', 'normalized_value']);
            $table->index(['contact_id', 'type', 'is_primary']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 16)->default('#64748b');
            $table->string('status', 32)->default('active')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type');
            $table->unsignedBigInteger('taggable_id');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });

        Schema::create('custom_fields', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('label');
            $table->string('key');
            $table->string('resource_type', 32)->index();
            $table->string('field_type', 32);
            $table->boolean('required')->default(false);
            $table->json('default_value')->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['resource_type', 'key']);
            $table->index(['resource_type', 'active', 'display_order']);
        });

        Schema::create('custom_field_values', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->string('resource_type');
            $table->unsignedBigInteger('resource_id');
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['custom_field_id', 'resource_type', 'resource_id'], 'custom_field_resource_unique');
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('customer_activities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->foreignId('contact_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('event_type', 64)->index();
            $table->string('description');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['contact_id', 'occurred_at']);
            $table->index(['company_id', 'occurred_at']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('customer_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('resource_type', 32)->default('contact');
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('failure_report')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_imports');
        Schema::dropIfExists('customer_activities');
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('contact_points');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
    }
};
