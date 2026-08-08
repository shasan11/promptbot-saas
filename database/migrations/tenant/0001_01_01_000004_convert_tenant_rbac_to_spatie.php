<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('guard_name')->default('tenant')->after('name');
            $table->boolean('is_protected')->default(false)->after('label');
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->string('guard_name')->default('tenant')->after('name');
            $table->string('group')->nullable()->after('label');
        });

        $this->dropUniqueIfExists('roles', ['roles_name_unique']);
        $this->dropUniqueIfExists('permissions', ['permissions_name_unique']);

        Schema::table('roles', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->primary(['permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });

        $userModel = 'App\\Models\\User';

        if (Schema::hasTable('role_user')) {
            DB::table('role_user')->orderBy('role_id')->orderBy('user_id')->chunk(200, function ($rows) use ($userModel): void {
                $now = now();
                DB::table('model_has_roles')->insertOrIgnore($rows->map(fn ($row) => [
                    'role_id' => $row->role_id,
                    'model_type' => $userModel,
                    'model_id' => $row->user_id,
                ])->all());
            });
        }

        if (Schema::hasTable('permission_role')) {
            DB::table('permission_role')->orderBy('permission_id')->orderBy('role_id')->chunk(200, function ($rows): void {
                DB::table('role_has_permissions')->insertOrIgnore($rows->map(fn ($row) => [
                    'permission_id' => $row->permission_id,
                    'role_id' => $row->role_id,
                ])->all());
            });
        }

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
    }

    public function down(): void
    {
        Schema::create('role_user', function (Blueprint $table): void {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn(['guard_name', 'is_protected']);
        });

        Schema::table('permissions', function (Blueprint $table): void {
            $table->dropUnique(['name', 'guard_name']);
            $table->dropColumn(['guard_name', 'group']);
        });
    }

    private function dropUniqueIfExists(string $table, array $indexNames): void
    {
        $existing = Schema::getIndexes($table);

        foreach ($indexNames as $indexName) {
            if (collect($existing)->pluck('name')->contains($indexName)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropUnique($indexName));
            }
        }
    }
};
