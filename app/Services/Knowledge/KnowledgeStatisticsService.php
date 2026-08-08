<?php

namespace App\Services\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\SourceStatus;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeSource;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the denormalised counters on knowledge bases and sources.
 *
 * Counting chunks live is the single most expensive query in the module — a
 * base with 80,000 chunks makes the index page unusable if every card triggers
 * one. These counters are recomputed (not incremented) after each processing
 * run, so they self-heal after a cascade delete or a partially failed job
 * rather than drifting permanently.
 */
class KnowledgeStatisticsService
{
    public function refreshForBase(KnowledgeBase $base): void
    {
        $sourceCounts = DB::table('knowledge_sources')
            ->selectRaw('count(*) as total, sum(case when status = ? then 1 else 0 end) as failed', [SourceStatus::Failed->value])
            ->where('knowledge_base_id', $base->id)
            ->whereNull('deleted_at')
            ->first();

        $documentCounts = DB::table('knowledge_documents')
            ->selectRaw('count(*) as total, coalesce(sum(file_size), 0) as bytes')
            ->where('knowledge_base_id', $base->id)
            ->whereNull('deleted_at')
            ->first();

        $chunkCount = DB::table('knowledge_chunks')
            ->where('knowledge_base_id', $base->id)
            ->where('is_retrievable', true)
            ->count();

        $base->forceFill([
            'source_count' => (int) ($sourceCounts->total ?? 0),
            'failed_source_count' => (int) ($sourceCounts->failed ?? 0),
            'document_count' => (int) ($documentCounts->total ?? 0),
            'storage_bytes' => (int) ($documentCounts->bytes ?? 0),
            'chunk_count' => $chunkCount,
            'counters_refreshed_at' => now(),
        ])->save();

        $this->reconcileBaseStatus($base);
    }

    public function refreshForSource(KnowledgeSource $source): void
    {
        $documents = DB::table('knowledge_documents')
            ->selectRaw('count(*) as total, coalesce(sum(file_size), 0) as bytes')
            ->where('knowledge_source_id', $source->id)
            ->whereNull('deleted_at')
            ->first();

        $source->forceFill([
            'document_count' => (int) ($documents->total ?? 0),
            'storage_bytes' => (int) ($documents->bytes ?? 0),
            'page_count' => DB::table('knowledge_website_pages')->where('knowledge_source_id', $source->id)->count(),
            'chunk_count' => DB::table('knowledge_chunks')
                ->where('knowledge_source_id', $source->id)
                ->where('is_retrievable', true)
                ->count(),
        ])->save();

        $this->reconcileSourceStatus($source);
    }

    /**
     * Derives a source's status from the state of its documents.
     *
     * Statuses an operator set deliberately (disabled, archived) are never
     * overwritten — automation must not silently re-enable something a person
     * switched off.
     */
    public function reconcileSourceStatus(KnowledgeSource $source): void
    {
        if (in_array($source->status, [SourceStatus::Disabled, SourceStatus::Archived, SourceStatus::AttentionRequired], true)) {
            return;
        }

        $counts = DB::table('knowledge_documents')
            ->selectRaw(
                'count(*) as total,'
                .' sum(case when status in (?, ?) then 1 else 0 end) as ready,'
                .' sum(case when status = ? then 1 else 0 end) as failed,'
                .' sum(case when status in (?, ?, ?, ?, ?, ?, ?) then 1 else 0 end) as in_flight',
                [
                    DocumentStatus::Ready->value, DocumentStatus::PartiallyReady->value,
                    DocumentStatus::Failed->value,
                    DocumentStatus::Queued->value, DocumentStatus::Validating->value,
                    DocumentStatus::Extracting->value, DocumentStatus::Processing->value,
                    DocumentStatus::Chunking->value, DocumentStatus::Embedding->value,
                    DocumentStatus::Indexing->value,
                ]
            )
            ->where('knowledge_source_id', $source->id)
            ->whereNull('deleted_at')
            ->first();

        $total = (int) ($counts->total ?? 0);
        $ready = (int) ($counts->ready ?? 0);
        $failed = (int) ($counts->failed ?? 0);
        $inFlight = (int) ($counts->in_flight ?? 0);

        $status = match (true) {
            $total === 0 => SourceStatus::Pending,
            $inFlight > 0 => SourceStatus::Processing,
            $failed === $total => SourceStatus::Failed,
            // Some content works and some does not: the source still answers,
            // but the failure has to stay visible rather than rounding to ready.
            $failed > 0 && $ready > 0 => SourceStatus::PartiallyReady,
            $ready === $total => SourceStatus::Ready,
            default => SourceStatus::AttentionRequired,
        };

        if ($status !== $source->status) {
            $source->forceFill(['status' => $status->value])->save();
        }
    }

    /** A base is healthy, busy, or degraded depending on its sources. */
    public function reconcileBaseStatus(KnowledgeBase $base): void
    {
        if (in_array($base->status, [KnowledgeBaseStatus::Disabled, KnowledgeBaseStatus::Archived, KnowledgeBaseStatus::Draft], true)) {
            return;
        }

        $counts = DB::table('knowledge_sources')
            ->selectRaw(
                'count(*) as total,'
                .' sum(case when status in (?, ?, ?) then 1 else 0 end) as busy,'
                .' sum(case when status in (?, ?, ?) then 1 else 0 end) as unhealthy',
                [
                    SourceStatus::Pending->value, SourceStatus::Queued->value, SourceStatus::Processing->value,
                    SourceStatus::Failed->value, SourceStatus::PartiallyReady->value, SourceStatus::AttentionRequired->value,
                ]
            )
            ->where('knowledge_base_id', $base->id)
            ->whereNull('deleted_at')
            ->first();

        $status = match (true) {
            (int) ($counts->total ?? 0) === 0 => KnowledgeBaseStatus::Draft,
            (int) ($counts->busy ?? 0) > 0 => KnowledgeBaseStatus::Processing,
            (int) ($counts->unhealthy ?? 0) > 0 => KnowledgeBaseStatus::Warning,
            default => KnowledgeBaseStatus::Active,
        };

        if ($status !== $base->status) {
            $base->forceFill(['status' => $status->value])->save();
        }
    }
}
