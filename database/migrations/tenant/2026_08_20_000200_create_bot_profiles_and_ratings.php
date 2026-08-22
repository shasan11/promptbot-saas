<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — makes AI behaviour configurable instead of hardcoded, and closes
 * the feedback loop.
 *
 * `bot_profiles` deliberately holds *behaviour* only (tone, length, when to
 * escalate) and not copy or appearance. Behaviour is the same wherever a bot
 * runs, so one profile can serve web chat, WhatsApp and Telegram at once —
 * whereas customer-facing wording is channel-specific ("leave a message" makes
 * sense in a widget and not over SMS) and stays on the channel's own settings
 * row. Splitting them this way is what stops the profile from becoming a
 * second, competing home for every widget setting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_profiles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');

            // --- Behaviour ---
            $table->string('tone', 32)->default('professional');
            $table->string('response_length', 32)->default('balanced');
            // match_customer = reply in whatever language they wrote in.
            $table->string('language_policy', 32)->default('match_customer');
            $table->string('default_language', 12)->default('en');

            // --- Escalation ---
            // Every one of these was a hardcoded constant in the orchestrator.
            $table->boolean('escalate_on_request')->default(true);
            $table->unsignedTinyInteger('escalate_after_failures')->default(2);
            $table->boolean('escalate_on_negative_sentiment')->default(true);
            $table->boolean('escalate_on_risk_flags')->default(true);
            $table->foreignId('escalation_team_id')->nullable()->constrained('teams')->nullOnDelete();

            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->foreignId('bot_profile_id')->nullable()->after('business_hours_policy_id')->constrained('bot_profiles')->nullOnDelete();
        });

        // Deflection: the single highest-leverage widget affordance, and the
        // only one that reliably stops a question being asked at all.
        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->json('suggested_questions')->nullable()->after('welcome_message');
        });

        Schema::create('conversation_ratings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            // 1-5 rather than a boolean thumb: a widget can send 5/1 for
            // thumbs today without the schema needing to change if a star
            // rating is offered later.
            $table->unsignedTinyInteger('score');
            $table->text('comment')->nullable();
            $table->timestamp('rated_at')->useCurrent();
            $table->timestamps();

            // One rating per conversation — a visitor changing their mind
            // updates rather than stacking duplicates that would skew CSAT.
            $table->unique('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_ratings');

        Schema::table('web_chat_widgets', function (Blueprint $table): void {
            $table->dropColumn('suggested_questions');
        });

        Schema::table('channels', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('bot_profile_id');
        });

        Schema::dropIfExists('bot_profiles');
    }
};
