<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When AI retrieval found nothing, the widget previously posted nothing at
 * all — the visitor watched a typing indicator resolve into silence, which
 * reads as a broken product rather than "we don't know". These make the
 * no-answer path an explicit, tenant-controlled response.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->text('no_answer_message')->nullable()->after('offline_message');
            // Whether an unanswered question should also pull a human in,
            // rather than only telling the visitor the bot cannot help.
            $table->boolean('handoff_on_no_answer')->default(true)->after('no_answer_message');
        });
    }

    public function down(): void
    {
        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->dropColumn(['no_answer_message', 'handoff_on_no_answer']);
        });
    }
};
