<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_refunds') && ! Schema::hasColumn('payment_refunds', 'idempotency_key')) {
            Schema::table('payment_refunds', function (Blueprint $table): void {
                $table->string('idempotency_key', 64)->nullable()->unique()->after('payment_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_refunds') && Schema::hasColumn('payment_refunds', 'idempotency_key')) {
            Schema::table('payment_refunds', fn (Blueprint $table) => $table->dropColumn('idempotency_key'));
        }
    }
};
