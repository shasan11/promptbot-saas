<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants') && ! Schema::hasColumn('tenants', 'region')) {
            Schema::table('tenants', fn (Blueprint $table) => $table->string('region', 100)->nullable()->after('company_name')->index());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'region')) {
            Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn('region'));
        }
    }
};
