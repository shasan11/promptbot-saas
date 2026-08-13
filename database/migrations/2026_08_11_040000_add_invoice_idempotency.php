<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoices') && ! Schema::hasColumn('invoices', 'idempotency_key')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'idempotency_key')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('idempotency_key'));
        }
    }
};
