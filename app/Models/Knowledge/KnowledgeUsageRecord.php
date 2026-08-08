<?php

namespace App\Models\Knowledge;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * Daily rollup of billable AI work (embedding tokens, OCR pages, re-rank calls).
 * Written incrementally on the processing path so the analytics page never has
 * to reconstruct cost by scanning chunk history.
 */
class KnowledgeUsageRecord extends Model
{
    public const OPERATION_EMBEDDING = 'embedding';

    public const OPERATION_OCR = 'ocr';

    public const OPERATION_RERANK = 'rerank';

    public const OPERATION_GENERATION = 'generation';

    protected $fillable = [
        'usage_date', 'knowledge_base_id', 'knowledge_source_id', 'provider',
        'operation', 'units', 'request_count', 'estimated_cost', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'estimated_cost' => 'decimal:6',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    /**
     * Adds usage to today's bucket for the given scope. Uses an atomic upsert
     * rather than read-modify-write: several embedding workers report against
     * the same bucket concurrently, and a lost update here would understate
     * cost silently.
     */
    public static function accrue(
        ?int $knowledgeBaseId,
        ?int $knowledgeSourceId,
        string $provider,
        string $operation,
        int $units,
        float $cost,
        int $requests = 1,
    ): void {
        $date = now()->toDateString();
        $dedupeKey = hash('sha256', implode('|', [
            $date, $knowledgeBaseId ?? '-', $knowledgeSourceId ?? '-', $provider, $operation,
        ]));

        static::query()->upsert([[
            'usage_date' => $date,
            'knowledge_base_id' => $knowledgeBaseId,
            'knowledge_source_id' => $knowledgeSourceId,
            'provider' => $provider,
            'operation' => $operation,
            'units' => $units,
            'request_count' => $requests,
            'estimated_cost' => $cost,
            'dedupe_key' => $dedupeKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['dedupe_key'], []);

        // upsert() with an empty update list is an insert-or-ignore, so the
        // accumulation happens here where it can be expressed as an atomic
        // column increment.
        static::query()->where('dedupe_key', $dedupeKey)->update([
            'units' => DB::raw('units + '.(int) $units),
            'request_count' => DB::raw('request_count + '.(int) $requests),
            'estimated_cost' => DB::raw('estimated_cost + '.number_format($cost, 6, '.', '')),
            'updated_at' => now(),
        ]);
    }
}
