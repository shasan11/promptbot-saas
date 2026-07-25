<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            $table->string('company_name');
            $table->string('slug')->unique();
            $table->string('status')->default('pending')->index();
            $table->uuid('plan_id')->nullable()->index();
            $table->string('provisioning_step')->nullable();
            $table->text('last_provisioning_error')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('tenancy_db_connection')->nullable();
            $table->string('tenancy_db_name')->nullable();
            $table->string('tenancy_db_host')->nullable();
            $table->unsignedInteger('tenancy_db_port')->nullable();
            $table->string('tenancy_db_username')->nullable();
            $table->text('tenancy_db_password')->nullable();
            $table->boolean('database_created_by_app')->default(false);

            $table->timestamps();
            $table->json('data')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
