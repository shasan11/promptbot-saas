<?php

namespace App\Services\Knowledge;

use App\Exceptions\Knowledge\QuotaExceededException;
use App\Models\Knowledge\KnowledgeUsageRecord;
use Illuminate\Support\Facades\DB;

/**
 * Tenant usage limits.
 *
 * Limits come from the tenant's subscription plan where it declares a knowledge
 * feature, and fall back to the platform defaults in config/knowledge.php
 * otherwise. Checks happen *before* expensive work starts — beginning a
 * 5,000-page crawl for a workspace already over quota burns real money on work
 * that has to be thrown away.
 */
class KnowledgeLimitService
{
    /** @return array<string, array{used: int, limit: int|null, exceeded: bool, percentage: float|null}> */
    public function usage(): array
    {
        $counts = [
            'knowledge_bases' => DB::table('knowledge_bases')->whereNull('deleted_at')->count(),
            'documents' => DB::table('knowledge_documents')->whereNull('deleted_at')->count(),
            'website_pages' => DB::table('knowledge_website_pages')->count(),
            'chunks' => DB::table('knowledge_chunks')->count(),
            'storage_bytes' => (int) DB::table('knowledge_documents')->whereNull('deleted_at')->sum('file_size'),
            'embedding_tokens_per_month' => (int) KnowledgeUsageRecord::query()
                ->where('operation', KnowledgeUsageRecord::OPERATION_EMBEDDING)
                ->where('usage_date', '>=', now()->startOfMonth())
                ->sum('units'),
            'crawl_pages_per_month' => DB::table('knowledge_website_pages')
                ->where('last_crawled_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $usage = [];

        foreach ($counts as $key => $used) {
            $limit = $this->limitFor($key);

            $usage[$key] = [
                'used' => $used,
                'limit' => $limit,
                'exceeded' => $limit !== null && $used >= $limit,
                'percentage' => $limit ? round(min(100, $used / $limit * 100), 1) : null,
            ];
        }

        return $usage;
    }

    public function limitFor(string $key): ?int
    {
        // A plan feature named e.g. "knowledge_documents" overrides the platform
        // default. Resolution is intentionally lenient: an install that has not
        // defined knowledge features still gets working limits.
        $feature = $this->planFeatureValue("knowledge_{$key}");

        if ($feature !== null) {
            return $feature < 0 ? null : $feature;
        }

        $default = config("knowledge.limits.{$key}");

        return $default === null ? null : (int) $default;
    }

    public function remaining(string $key): ?int
    {
        $limit = $this->limitFor($key);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - ($this->usage()[$key]['used'] ?? 0));
    }

    /**
     * Throws if the limit is already met or would be exceeded by `$additional`.
     *
     * @throws QuotaExceededException
     */
    public function assertWithinLimit(string $key, int $additional = 1): void
    {
        $limit = $this->limitFor($key);

        if ($limit === null) {
            return;
        }

        $used = $this->usage()[$key]['used'] ?? 0;

        if ($used + $additional > $limit) {
            throw QuotaExceededException::forLimit($key, $used + $additional, $limit);
        }
    }

    public function isExceeded(string $key): bool
    {
        return ($this->usage()[$key]['exceeded'] ?? false) === true;
    }

    private function planFeatureValue(string $slug): ?int
    {
        if (! tenancy()->initialized) {
            return null;
        }

        try {
            // TenantFeatureService::limit() returns null both for "no such
            // feature" and for "unlimited"; only a feature the plan actually
            // declares should override the platform default, so the existence
            // check comes first.
            $service = app(\App\Services\SaaS\TenantFeatureService::class);

            if (! $service->enabled($slug)) {
                return null;
            }

            $limit = $service->limit($slug);

            return $limit === null ? -1 : $limit;
        } catch (\Throwable) {
            // Feature resolution is an optimisation, not a gate. If the SaaS
            // layer cannot answer, fall back to platform limits rather than
            // blocking every upload in the workspace.
            return null;
        }
    }
}
