<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "who is answering this conversation" explicit.
 *
 * Backfill matters here: existing conversations must not all wake up as
 * `ai` and start getting automated replies on threads a human already owns.
 * Any conversation that already has an outbound message from a real user is
 * therefore seeded as `human`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('control_state', 24)->default('ai')->after('status');
            $table->timestamp('control_changed_at')->nullable()->after('control_state');
            // Drives "hand off after N unanswerable questions" without having
            // to re-read the whole message history on every inbound message.
            $table->unsignedSmallInteger('ai_failure_count')->default(0)->after('control_changed_at');

            $table->index(['control_state', 'status'], 'conversations_control_status_idx');
        });

        // A conversation a person has already replied to is theirs.
        DB::table('conversations')
            ->whereIn('id', fn ($query) => $query
                ->select('conversation_id')
                ->from('messages')
                ->where('direction', 'outbound')
                ->where('sender_type', 'user'))
            ->update(['control_state' => 'human', 'control_changed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex('conversations_control_status_idx');
            $table->dropColumn(['control_state', 'control_changed_at', 'ai_failure_count']);
        });
    }
};
