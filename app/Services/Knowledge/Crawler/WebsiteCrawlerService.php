<?php

namespace App\Services\Knowledge\Crawler;

use App\Enums\Knowledge\ProcessingStage;
use App\Exceptions\Knowledge\UnsafeUrlException;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Models\Knowledge\KnowledgeWebsitePage;
use App\Services\Knowledge\Extraction\HtmlExtractor;
use App\Services\Knowledge\ProcessingLogger;
use App\Services\Knowledge\Support\ContentNormaliser;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Breadth-first website crawler with change detection.
 *
 * Behaviour worth knowing about:
 *
 *  - Every URL is re-validated by UrlSafetyGuard immediately before it is
 *    fetched, and again after each redirect hop. Redirects are followed
 *    manually for that reason — handing the redirect chain to the HTTP client
 *    would validate only the first URL.
 *  - Pages are fingerprinted. A re-crawl that finds identical content marks the
 *    page unchanged and skips extraction, chunking and embedding entirely, so
 *    a weekly sync of a large site costs almost nothing.
 *  - Pages that vanish are marked missing and withdrawn from retrieval, so a
 *    deleted help article stops being quoted to customers.
 *  - The crawl is bounded by page count, depth, path rules, response size and a
 *    per-request delay. All of those are limits, not preferences: an unbounded
 *    crawler is a denial-of-service tool aimed at someone else's website.
 */
class WebsiteCrawlerService
{
    public function __construct(
        private readonly UrlSafetyGuard $guard,
        private readonly UrlNormaliser $urls,
        private readonly HtmlExtractor $html,
        private readonly ProcessingLogger $logger,
    ) {}

    /**
     * @return array{discovered: int, fetched: int, created: int, updated: int, unchanged: int, missing: int, failed: int, skipped: int}
     */
    public function crawl(KnowledgeSource $source, KnowledgeSyncRun $run, ?KnowledgeProcessingJob $job = null): array
    {
        $logger = $this->logger->forJob($job);
        $config = $this->configuration($source);

        $stats = ['discovered' => 0, 'fetched' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'failed' => 0, 'skipped' => 0];

        $rootUrl = $this->urls->normalise((string) $config['root_url']);

        if (! $rootUrl) {
            throw UnsafeUrlException::blocked((string) $config['root_url'], 'malformed URL');
        }

        $this->guard->assertSafe($rootUrl);

        $disallowedByRobots = $config['respect_robots'] ? $this->robotsDisallowRules($rootUrl) : [];

        // Seeds: the sitemap when asked for, otherwise the root page.
        $queue = [];
        $seen = [];

        foreach ($this->seedUrls($source, $config, $rootUrl) as $seed) {
            $key = $this->urls->hash($seed);
            $queue[] = ['url' => $seed, 'depth' => 0];
            $seen[$key] = true;
        }

        $touchedHashes = [];
        $maxPages = (int) $config['max_pages'];
        $delayMicroseconds = (int) config('knowledge.crawler.delay_between_requests_ms') * 1000;

        while ($queue && $stats['fetched'] < $maxPages) {
            // Cooperative cancellation — checked per page so "Cancel" on the
            // Processing Queue stops real work rather than just hiding a row.
            if ($job?->isCancelled()) {
                $logger->warn(null, ProcessingStage::Extracting, 'Crawl cancelled by operator', $stats);
                break;
            }

            $current = array_shift($queue);
            $url = $current['url'];
            $depth = $current['depth'];

            if (! $this->isAllowedPath($url, $config, $disallowedByRobots)) {
                $stats['skipped']++;

                continue;
            }

            try {
                $this->guard->assertSafe($url);
                $response = $this->fetch($url);
            } catch (UnsafeUrlException $e) {
                $stats['skipped']++;
                $logger->warn(null, ProcessingStage::Extracting, 'Skipped unsafe URL', ['depth' => $depth]);

                continue;
            } catch (Throwable $e) {
                $stats['failed']++;
                $this->recordFailedPage($source, $url, $depth, $e->getMessage());

                continue;
            }

            $stats['fetched']++;
            $stats['discovered']++;

            if ($response === null || ! $response->successful()) {
                $stats['failed']++;
                $this->recordFailedPage($source, $url, $depth, 'HTTP '.($response?->status() ?? 'error'));

                continue;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            // Only HTML carries links and readable prose. PDFs linked from a
            // site are a separate, deliberate ingestion decision.
            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'xhtml')) {
                $stats['skipped']++;

                continue;
            }

            $body = $response->body();
            $outcome = $this->storePage($source, $url, $body, $depth, $response->status());

            $stats[$outcome['result']]++;
            $touchedHashes[] = $outcome['url_hash'];

            if ($depth < (int) $config['max_depth']) {
                foreach ($this->extractLinks($body, $url, $rootUrl, $config) as $link) {
                    $key = $this->urls->hash($link);

                    if (isset($seen[$key])) {
                        continue;
                    }

                    $seen[$key] = true;
                    $queue[] = ['url' => $link, 'depth' => $depth + 1];
                }
            }

            if ($delayMicroseconds > 0) {
                // Politeness, and self-preservation: hammering a site gets our
                // crawler blocked and the tenant's source permanently broken.
                usleep($delayMicroseconds);
            }
        }

        $stats['missing'] = $this->markMissingPages($source, $touchedHashes);

        $logger->stage(null, ProcessingStage::Indexing, 'Website crawl finished', $stats);

        return $stats;
    }

    /**
     * Fetches one URL, following redirects by hand so every hop is re-validated
     * and the response body stays bounded.
     */
    private function fetch(string $url): ?Response
    {
        $maxRedirects = (int) config('knowledge.crawler.max_redirects');
        $maxBytes = (int) config('knowledge.crawler.max_response_bytes');

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $response = Http::withHeaders([
                'User-Agent' => (string) config('knowledge.crawler.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->timeout((int) config('knowledge.crawler.request_timeout'))
                ->withoutRedirecting()
                ->get($url);

            if (! $response->redirect()) {
                // A server may lie about Content-Length, so the body is measured
                // after the fact as well.
                if (strlen($response->body()) > $maxBytes) {
                    return null;
                }

                return $response;
            }

            $location = $response->header('Location');

            if (! $location) {
                return $response;
            }

            $next = $this->urls->normalise($location, $url);

            if (! $next) {
                return null;
            }

            // The critical re-check: a public URL redirecting to
            // 169.254.169.254 must be refused here, not followed.
            $this->guard->assertSafe($next);
            $url = $next;
        }

        // Exhausting the hop budget means a redirect loop.
        return null;
    }

    /**
     * Persists a crawled page, deciding whether it is new, changed or unchanged.
     *
     * @return array{result: string, url_hash: string}
     */
    private function storePage(KnowledgeSource $source, string $url, string $body, int $depth, int $status): array
    {
        $urlHash = $this->urls->hash($url);
        $extracted = $this->html->extractFromHtml($body);
        $text = ContentNormaliser::normalise($extracted->text);
        $contentHash = ContentNormaliser::hash($text);

        $page = KnowledgeWebsitePage::query()
            ->where('knowledge_source_id', $source->id)
            ->where('url_hash', $urlHash)
            ->first();

        $canonical = $extracted->metadata['canonical_url'] ?? null;
        $canonical = $canonical ? $this->urls->normalise($canonical, $url) : null;

        if ($page && $page->content_hash === $contentHash) {
            $page->forceFill([
                'last_crawled_at' => now(),
                'http_status' => $status,
                'status' => KnowledgeWebsitePage::STATUS_UNCHANGED,
                'missing_since' => null,
                'last_error' => null,
            ])->save();

            return ['result' => 'unchanged', 'url_hash' => $urlHash];
        }

        $isNew = $page === null;

        $page ??= new KnowledgeWebsitePage([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'url' => $url,
            'url_hash' => $urlHash,
        ]);

        $page->forceFill([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'url' => $url,
            'url_hash' => $urlHash,
            'canonical_url' => $canonical,
            'page_title' => Str::limit($extracted->detectedTitle ?? $url, 200, ''),
            'meta_description' => $extracted->metadata['meta_description'] ?? null,
            'content_hash' => $contentHash,
            'http_status' => $status,
            'depth' => $depth,
            'language' => $extracted->metadata['html_lang'] ?? null,
            'word_count' => $extracted->wordCount(),
            'status' => KnowledgeWebsitePage::STATUS_FETCHED,
            'first_seen_at' => $page->first_seen_at ?? now(),
            'last_crawled_at' => now(),
            'last_changed_at' => now(),
            'missing_since' => null,
            'last_error' => null,
        ])->save();

        // The page body becomes a document, so it runs the same extraction →
        // chunking → embedding path as an uploaded file rather than a parallel
        // one that would need its own versioning and citation handling.
        $document = $page->knowledge_document_id
            ? KnowledgeDocument::find($page->knowledge_document_id)
            : null;

        $document ??= new KnowledgeDocument([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $source->knowledge_collection_id,
            'kind' => KnowledgeDocument::KIND_WEBSITE_PAGE,
        ]);

        $document->forceFill([
            'knowledge_base_id' => $source->knowledge_base_id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $source->knowledge_collection_id,
            'kind' => KnowledgeDocument::KIND_WEBSITE_PAGE,
            'title' => $page->page_title,
            'extracted_text' => $text,
            // Cleared so the pipeline sees changed content and re-chunks. The
            // hash it eventually stores is computed from the same normalised
            // text, so the next unchanged crawl short-circuits correctly.
            'content_hash' => null,
            'language' => $page->language,
            'status' => \App\Enums\Knowledge\DocumentStatus::Queued->value,
            'current_stage' => ProcessingStage::Uploaded->value,
        ]);

        if (! $document->exists) {
            $document->uuid = (string) Str::uuid();
        }

        $document->save();

        $page->forceFill(['knowledge_document_id' => $document->id])->save();

        return ['result' => $isNew ? 'created' : 'updated', 'url_hash' => $urlHash];
    }

    /**
     * Pages present in a previous crawl but not this one are marked missing and
     * withdrawn from retrieval — a deleted help article must stop answering.
     *
     * @param  array<int, string>  $touchedHashes
     */
    private function markMissingPages(KnowledgeSource $source, array $touchedHashes): int
    {
        $query = KnowledgeWebsitePage::query()
            ->where('knowledge_source_id', $source->id)
            ->whereNotIn('status', [KnowledgeWebsitePage::STATUS_MISSING]);

        if ($touchedHashes) {
            $query->whereNotIn('url_hash', $touchedHashes);
        }

        $missing = $query->get();

        foreach ($missing as $page) {
            $page->forceFill([
                'status' => KnowledgeWebsitePage::STATUS_MISSING,
                'missing_since' => $page->missing_since ?? now(),
            ])->save();

            if ($page->knowledge_document_id) {
                \App\Models\Knowledge\KnowledgeChunk::query()
                    ->where('owner_key', 'document:'.$page->knowledge_document_id)
                    ->update(['is_retrievable' => false]);
            }
        }

        return $missing->count();
    }

    private function recordFailedPage(KnowledgeSource $source, string $url, int $depth, string $error): void
    {
        $urlHash = $this->urls->hash($url);

        KnowledgeWebsitePage::query()->updateOrCreate(
            ['knowledge_source_id' => $source->id, 'url_hash' => $urlHash],
            [
                'knowledge_base_id' => $source->knowledge_base_id,
                'url' => $url,
                'depth' => $depth,
                'status' => KnowledgeWebsitePage::STATUS_FAILED,
                'last_error' => Str::limit($error, 500),
                'last_crawled_at' => now(),
                'first_seen_at' => now(),
            ]
        );
    }

    /** @return array<int, string> */
    private function seedUrls(KnowledgeSource $source, array $config, string $rootUrl): array
    {
        if (! $config['use_sitemap']) {
            return [$rootUrl];
        }

        $sitemapUrl = $config['sitemap_url'] ?: rtrim($rootUrl, '/').'/sitemap.xml';

        try {
            $this->guard->assertSafe($sitemapUrl);
            $urls = $this->readSitemap($sitemapUrl, 0);
        } catch (Throwable) {
            $urls = [];
        }

        // A missing or unreadable sitemap is not fatal — fall back to crawling
        // from the root.
        return $urls ?: [$rootUrl];
    }

    /**
     * Reads a sitemap, following sitemap-index files one level deep.
     *
     * @return array<int, string>
     */
    private function readSitemap(string $url, int $depth): array
    {
        if ($depth > 1) {
            return [];
        }

        $response = Http::withHeaders(['User-Agent' => (string) config('knowledge.crawler.user_agent')])
            ->timeout((int) config('knowledge.crawler.request_timeout'))
            ->get($url);

        if (! $response->successful()) {
            return [];
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;
            $document->loadXML($response->body(), LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $urls = [];
        $isIndex = $document->getElementsByTagName('sitemapindex')->length > 0;

        foreach ($document->getElementsByTagName('loc') as $node) {
            $location = $this->urls->normalise(trim((string) $node->textContent));

            if (! $location) {
                continue;
            }

            if ($isIndex) {
                try {
                    $this->guard->assertSafe($location);
                    $urls = array_merge($urls, $this->readSitemap($location, $depth + 1));
                } catch (Throwable) {
                    continue;
                }

                continue;
            }

            $urls[] = $location;
        }

        return array_slice(array_unique($urls), 0, (int) config('knowledge.crawler.max_pages_limit'));
    }

    /** @return array<int, string> */
    private function extractLinks(string $html, string $currentUrl, string $rootUrl, array $config): array
    {
        if (! preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            return [];
        }

        $links = [];

        foreach ($matches[1] as $href) {
            $normalised = $this->urls->normalise(html_entity_decode($href), $currentUrl);

            if (! $normalised) {
                continue;
            }

            // External links are off by default: following them turns "index my
            // help site" into "crawl the internet from our IP".
            if (! $this->urls->sameHost($normalised, $rootUrl, (bool) $config['include_subdomains'])) {
                if (! $config['follow_external']) {
                    continue;
                }
            }

            $links[] = $normalised;
        }

        return array_values(array_unique($links));
    }

    /** @param  array<int, string>  $robotsDisallow */
    private function isAllowedPath(string $url, array $config, array $robotsDisallow): bool
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');

        foreach ($config['excluded_paths'] as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return false;
            }
        }

        foreach ($robotsDisallow as $pattern) {
            if ($pattern !== '' && str_starts_with($path, $pattern)) {
                return false;
            }
        }

        // An empty allow-list means "everything not excluded".
        if (! $config['allowed_paths']) {
            return true;
        }

        foreach ($config['allowed_paths'] as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** Glob-style patterns: /docs/*, /help/* */
    private function matchesPattern(string $path, string $pattern): bool
    {
        $pattern = trim($pattern);

        if ($pattern === '') {
            return false;
        }

        return fnmatch($pattern, $path, FNM_CASEFOLD)
            || str_starts_with($path, rtrim($pattern, '*'));
    }

    /**
     * Disallow rules for our own user-agent (and the `*` group) from robots.txt.
     *
     * @return array<int, string>
     */
    private function robotsDisallowRules(string $rootUrl): array
    {
        try {
            $robotsUrl = rtrim($rootUrl, '/').'/robots.txt';
            $this->guard->assertSafe($robotsUrl);

            $response = Http::withHeaders(['User-Agent' => (string) config('knowledge.crawler.user_agent')])
                ->timeout(10)
                ->get($robotsUrl);

            if (! $response->successful()) {
                return [];
            }
        } catch (Throwable) {
            // No robots.txt means no restrictions, which is the standard's own
            // interpretation of a 404.
            return [];
        }

        $rules = [];
        $applies = false;

        foreach (preg_split('/\R/', $response->body()) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');

            if ($line === '') {
                continue;
            }

            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $agent = trim($matches[1]);
                $applies = $agent === '*' || stripos((string) config('knowledge.crawler.user_agent'), $agent) !== false;

                continue;
            }

            if ($applies && preg_match('/^Disallow:\s*(.*)$/i', $line, $matches)) {
                $rules[] = trim($matches[1]);
            }
        }

        return array_filter($rules);
    }

    /** @return array<string, mixed> */
    private function configuration(KnowledgeSource $source): array
    {
        $maxPages = (int) $source->configValue('crawl.max_pages', config('knowledge.crawler.default_max_pages'));
        $maxDepth = (int) $source->configValue('crawl.max_depth', config('knowledge.crawler.default_max_depth'));

        return [
            'root_url' => $source->configValue('url'),
            // Tenant values are clamped to platform ceilings — a tenant must not
            // be able to configure a 500,000-page crawl.
            'max_pages' => max(1, min($maxPages, (int) config('knowledge.crawler.max_pages_limit'))),
            'max_depth' => max(0, min($maxDepth, (int) config('knowledge.crawler.max_depth_limit'))),
            'allowed_paths' => (array) $source->configValue('crawl.allowed_paths', []),
            'excluded_paths' => (array) $source->configValue('crawl.excluded_paths', []),
            'include_subdomains' => (bool) $source->configValue('crawl.include_subdomains', false),
            'follow_external' => (bool) $source->configValue('crawl.follow_external', false),
            'respect_robots' => (bool) $source->configValue('crawl.respect_robots', config('knowledge.crawler.respect_robots_by_default')),
            'use_sitemap' => (bool) $source->configValue('crawl.use_sitemap', false),
            'sitemap_url' => $source->configValue('crawl.sitemap_url'),
        ];
    }
}
