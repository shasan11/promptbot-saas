<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_webhook_events')) {
            Schema::create('payment_webhook_events', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('provider')->index();
                $table->string('provider_event_id');
                $table->string('type')->index();
                $table->string('status')->default('processing')->index();
                $table->uuid('invoice_id')->nullable()->index();
                $table->uuid('payment_id')->nullable()->index();
                $table->string('payload_hash', 64);
                $table->text('failure_reason')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_event_id']);
                $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
                $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
