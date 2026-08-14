<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->boolean('ai_auto_reply_enabled')->default(false)->after('active');
            $table->foreignId('knowledge_base_id')->nullable()->after('ai_auto_reply_enabled')->constrained('knowledge_bases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('knowledge_base_id');
            $table->dropColumn('ai_auto_reply_enabled');
        });
    }
};
