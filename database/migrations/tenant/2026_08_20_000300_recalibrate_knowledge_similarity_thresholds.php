<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs knowledge bases left on the old, mis-calibrated 0.70 threshold.
 *
 * Measured against real 3072-dim embeddings on a live support corpus,
 * genuinely relevant paraphrases score cosine 0.539–0.754 and off-topic
 * questions 0.463–0.494. A 0.70 cut-off therefore discarded 7 of every 8
 * correct answers — the retrieval was finding the right passage and the
 * threshold was throwing it away, which is what surfaced as the bot
 * "not knowing" things that are plainly documented.
 *
 * Only bases still holding exactly the old default are touched. A value an
 * operator deliberately tuned is left alone: silently overriding a
 * considered setting is worse than leaving it, and anyone who changed it
 * away from 0.70 has already formed an opinion.
 */
return new class extends Migration
{
    public function up(): void
    {
        $semantic = (float) config('knowledge.retrieval.default_similarity_threshold', 0.52);
        $local = (float) config('knowledge.retrieval.default_similarity_threshold_local', 0.05);

        // The built-in hash provider produces a much lower score band, so the
        // semantic default would reject everything it retrieves.
        DB::table('knowledge_bases')
            ->where('similarity_threshold', 0.70)
            ->where('embedding_provider', 'local')
            ->update(['similarity_threshold' => $local, 'updated_at' => now()]);

        DB::table('knowledge_bases')
            ->where('similarity_threshold', 0.70)
            ->where('embedding_provider', '!=', 'local')
            ->update(['similarity_threshold' => $semantic, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Restores the previous default only for bases still carrying exactly
        // the values this migration wrote.
        DB::table('knowledge_bases')
            ->whereIn('similarity_threshold', [
                (float) config('knowledge.retrieval.default_similarity_threshold', 0.52),
                (float) config('knowledge.retrieval.default_similarity_threshold_local', 0.05),
            ])
            ->update(['similarity_threshold' => 0.70, 'updated_at' => now()]);
    }
};
