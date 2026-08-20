<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Non-secret configuration for the three Meta-backed channels, following the
 * same 1:1-per-channel-type pattern as `email_channel_settings`. Secrets
 * (access token, app secret, verify token) live in `channel_credentials`,
 * not here — same split the email channel already uses for SMTP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('phone_number_id');
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('display_phone_number')->nullable();
            $table->timestamps();
        });

        Schema::create('messenger_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('page_id');
            $table->string('page_name')->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('instagram_business_account_id');
            $table->string('page_id');
            $table->string('username')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_channel_settings');
        Schema::dropIfExists('messenger_channel_settings');
        Schema::dropIfExists('whatsapp_channel_settings');
    }
};
