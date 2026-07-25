<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name')->default('central');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('platform_roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name')->default('central');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create('platform_model_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('platform_permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type'], 'platform_model_permissions_model_index');
            $table->primary(['permission_id', 'model_id', 'model_type'], 'platform_model_permissions_primary');
        });

        Schema::create('platform_model_has_roles', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_id');
            $table->index(['model_id', 'model_type'], 'platform_model_roles_model_index');
            $table->primary(['role_id', 'model_id', 'model_type'], 'platform_model_roles_primary');
        });

        Schema::create('platform_role_has_permissions', function (Blueprint $table): void {
            $table->foreignUuid('permission_id')->constrained('platform_permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('platform_roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'platform_role_permissions_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_role_has_permissions');
        Schema::dropIfExists('platform_model_has_roles');
        Schema::dropIfExists('platform_model_has_permissions');
        Schema::dropIfExists('platform_roles');
        Schema::dropIfExists('platform_permissions');
    }
};
