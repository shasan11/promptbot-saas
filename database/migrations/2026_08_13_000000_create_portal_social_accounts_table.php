<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('portal_social_accounts')) {
            return;
        }

        Schema::create('portal_social_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('provider_user_id', 255);
            $table->string('provider_email')->nullable()->index();
            $table->string('avatar_url', 2048)->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_user_id']);
            $table->unique(['portal_user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_social_accounts');
    }
};
