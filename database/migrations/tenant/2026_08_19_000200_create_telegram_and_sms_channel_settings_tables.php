<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            // Populated automatically from Telegram's getMe response, not
            // user-entered — the bot token itself is the only thing the
            // tenant provides.
            $table->string('bot_username')->nullable();
            $table->string('bot_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sms_channel_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('channel_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('from_number');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_channel_settings');
        Schema::dropIfExists('telegram_channel_settings');
    }
};
