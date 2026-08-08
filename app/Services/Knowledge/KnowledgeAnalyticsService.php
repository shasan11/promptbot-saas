<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\SourceStatus;
use App\Models\Knowledge\KnowledgeGap;
use App\Models\Knowledge\KnowledgeRetrievalLog;
use App\Models\Knowledge\KnowledgeUsageRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read models for the overview and analytics screens.
 *
 * Every figure here is derived from data the pipeline actually writes. Nothing
 * is estimated or hard-coded — an empty workspace shows zeros, not a demo
 * dashboard, because a metric a user cannot trust is worse than no metric.
 */
class KnowledgeAnalyticsService
{
    /**
     * Headline counters for /knowledge.
     *
     * @param  array<int, int>  $allowedBaseIds
     * @return array<string, mixed>
     */
    public function overview(array $allowedBaseIds): array
    {
        if (! $allowedBaseIds) {
            return $this->emptyOverview();
        }

        $bases = DB::table('knowledge_bases')
            ->selectRaw('count(*) as total, coalesce(sum(chunk_count),0) as chunks, coalesce(sum(storage_bytes),0) as storage, coalesce(sum(document_count),0) as documents')
            ->whereIn('id', $allowedBaseIds)
            ->whereNull('deleted_at')
            ->first();

        $sources = DB::table('knowledge_sources')
            ->selectRaw('status, count(*) as total')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->whereNull('deleted_at')
            ->groupBy('status')
            ->pluck('total', 'status');

        $health = ['healthy' => 0, 'processing' => 0, 'needs_attention' => 0, 'failed' => 0, 'inactive' => 0];

        foreach ($sources as $status => $count) {
            $bucket = SourceStatus::tryFrom((string) $status)?->healthBucket() ?? 'needs_attention';
            $health[$bucket] += (int) $count;
        }

        $outdated = DB::table('knowledge_sources')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->whereNull('deleted_at')
            ->whereNotNull('review_due_at')
            ->where('review_due_at', '<=', now())
            ->count();

        $lastSync = DB::table('knowledge_sources')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->max('last_successful_sync_at');

        return [
            'knowledge_bases' => (int) ($bases->total ?? 0),
            'active_sources' => array_sum($sources->toArray()),
            'indexed_documents' => (int) ($bases->documents ?? 0),
            'total_chunks' => (int) ($bases->chunks ?? 0),
            'storage_bytes' => (int) ($bases->storage ?? 0),
            'failed_sources' => $health['failed'],
            'last_synced_at' => $lastSync ? Carbon::parse($lastSync)->toIso8601String() : null,
            'retrieval_success_rate' => $this->retrievalSuccessRate($allowedBaseIds, 30),
            'health' => $health + ['outdated' => $outdated],
        ];
    }

    /**
     * Share of retrievals in the window that returned at least one result above
     * the threshold. This is the number that tells an owner whether their
     * knowledge base is actually working.
     *
     * @param  array<int, int>  $allowedBaseIds
     */
    public function retrievalSuccessRate(array $allowedBaseIds, int $days = 30): ?float
    {
        $row = DB::table('knowledge_retrieval_logs')
            ->selectRaw('count(*) as total, sum(case when zero_results = 0 then 1 else 0 end) as answered')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->where('created_at', '>=', now()->subDays($days))
            ->first();

        $total = (int) ($row->total ?? 0);

        // Null, not 100% — no traffic is not the same as perfect performance,
        // and showing "100%" for an unused base is actively misleading.
        return $total === 0 ? null : round(((int) $row->answered / $total) * 100, 1);
    }

    /**
     * @param  array<int, int>  $allowedBaseIds
     * @return array<string, mixed>
     */
    public function analytics(array $allowedBaseIds, int $days = 30): array
    {
        if (! $allowedBaseIds) {
            return ['totals' => [], 'daily' => [], 'top_queries' => [], 'unanswered' => [], 'top_documents' => [], 'cost' => []];
        }

        $since = now()->subDays($days);

        $totals = DB::table('knowledge_retrieval_logs')
            ->selectRaw(
                'count(*) as searches,'
                .' sum(case when zero_results = 1 then 1 else 0 end) as zero_results,'
                .' sum(case when below_threshold = 1 then 1 else 0 end) as weak_results,'
                .' avg(top_score) as average_top_score,'
                .' avg(total_ms) as average_latency_ms,'
                .' max(total_ms) as slowest_ms'
            )
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->where('created_at', '>=', $since)
            ->first();

        $daily = DB::table('knowledge_retrieval_logs')
            ->selectRaw('date(created_at) as day, count(*) as searches, sum(case when zero_results = 1 then 1 else 0 end) as zero_results, avg(total_ms) as latency')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->where('created_at', '>=', $since)
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $topQueries = DB::table('knowledge_retrieval_logs')
            ->selectRaw('min(query) as query, count(*) as occurrences, avg(top_score) as average_score')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->where('created_at', '>=', $since)
            ->groupBy('query_hash')
            ->orderByDesc('occurrences')
            ->limit(20)
            ->get();

        // Which documents actually earn their keep. A knowledge base where 90%
        // of documents never appear in a result is telling its owner something.
        $topDocuments = DB::table('knowledge_retrieval_results')
            ->join('knowledge_chunks', 'knowledge_chunks.id', '=', 'knowledge_retrieval_results.knowledge_chunk_id')
            ->leftJoin('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.knowledge_document_id')
            ->selectRaw('knowledge_documents.uuid, knowledge_documents.title, count(*) as retrievals')
            ->whereIn('knowledge_chunks.knowledge_base_id', $allowedBaseIds)
            ->where('knowledge_retrieval_results.included_in_context', true)
            ->where('knowledge_retrieval_results.created_at', '>=', $since)
            ->whereNotNull('knowledge_documents.id')
            ->groupBy('knowledge_documents.uuid', 'knowledge_documents.title')
            ->orderByDesc('retrievals')
            ->limit(15)
            ->get();

        $cost = KnowledgeUsageRecord::query()
            ->selectRaw('operation, provider, sum(units) as units, sum(request_count) as requests, sum(estimated_cost) as cost')
            ->whereIn('knowledge_base_id', $allowedBaseIds)
            ->where('usage_date', '>=', $since->toDateString())
            ->groupBy('operation', 'provider')
            ->get();

        return [
            'totals' => [
                'searches' => (int) ($totals->searches ?? 0),
                'zero_results' => (int) ($totals->zero_results ?? 0),
                'weak_results' => (int) ($totals->weak_results ?? 0),
                'average_top_score' => $totals->average_top_score ? round((float) $totals->average_top_score, 3) : null,
                'average_latency_ms' => $totals->average_latency_ms ? (int) round((float) $totals->average_latency_ms) : null,
                'slowest_ms' => (int) ($totals->slowest_ms ?? 0),
                'success_rate' => $this->retrievalSuccessRate($allowedBaseIds, $days),
            ],
            'daily' => $daily,
            'top_queries' => $topQueries,
            'top_documents' => $topDocuments,
            'cost' => $cost,
            'unanswered' => $this->knowledgeGaps($allowedBaseIds),
        ];
    }

    /**
     * Open knowledge gaps, most frequent first — the actionable half of
     * analytics.
     *
     * @param  array<int, int>  $allowedBaseIds
     */
    public function knowledgeGaps(array $allowedBaseIds, int $limit = 25): \Illuminate\Support\Collection
    {
        return KnowledgeGap::query()
            ->with('knowledgeBase:id,uuid,name')
            ->open()
            ->where(fn ($q) => $q->whereIn('knowledge_base_id', $allowedBaseIds)->orWhereNull('knowledge_base_id'))
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Recent activity for the overview feed.
     *
     * @param  array<int, int>  $allowedBaseIds
     * @return array<int, array<string, mixed>>
     */
    public function recentActivity(array $allowedBaseIds, int $limit = 15): array
    {
        if (! $allowedBaseIds) {
            return [];
        }

        $logs = DB::table('knowledge_processing_logs')
            ->leftJoin('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_processing_logs.knowledge_document_id')
            ->leftJoin('knowledge_sources', 'knowledge_sources.id', '=', 'knowledge_processing_logs.knowledge_source_id')
            ->select([
                'knowledge_processing_logs.stage', 'knowledge_processing_logs.level',
                'knowledge_processing_logs.message', 'knowledge_processing_logs.created_at',
                'knowledge_documents.uuid as document_uuid', 'knowledge_documents.title as document_title',
                'knowledge_sources.uuid as source_uuid', 'knowledge_sources.name as source_name',
            ])
            ->whereIn('knowledge_sources.knowledge_base_id', $allowedBaseIds)
            ->orderByDesc('knowledge_processing_logs.created_at')
            ->limit($limit)
            ->get();

        return $logs->map(fn ($row) => [
            'stage' => $row->stage,
            'level' => $row->level,
            'message' => $row->message,
            'entity' => $row->document_title ?? $row->source_name,
            'document_uuid' => $row->document_uuid,
            'source_uuid' => $row->source_uuid,
            'created_at' => $row->created_at,
        ])->all();
    }

    /** @return array<string, mixed> */
    private function emptyOverview(): array
    {
        return [
            'knowledge_bases' => 0,
            'active_sources' => 0,
            'indexed_documents' => 0,
            'total_chunks' => 0,
            'storage_bytes' => 0,
            'failed_sources' => 0,
            'last_synced_at' => null,
            'retrieval_success_rate' => null,
            'health' => ['healthy' => 0, 'processing' => 0, 'needs_attention' => 0, 'failed' => 0, 'inactive' => 0, 'outdated' => 0],
        ];
    }
}
