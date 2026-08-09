<?php

use App\Enums\Knowledge\ChunkingStrategy;
use App\Enums\Knowledge\RetrievalMode;

/*
|--------------------------------------------------------------------------
| Knowledge Base module
|--------------------------------------------------------------------------
|
| Platform-level ceilings and defaults for the Knowledge Base module. Tenants
| override the *defaults* through Knowledge Settings, but never the *limits* —
| App\Services\Knowledge\KnowledgeLimitService clamps every tenant value to the
| bounds declared here so one workspace cannot configure itself into starving
| the shared queue workers or the embedding provider quota.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Original uploads are private objects. `disk` should point at S3-compatible
    | storage in production; the local disk is fine for single-server installs.
    | Files are laid out as tenants/{tenant}/knowledge/{base_uuid}/{document_uuid}.
    |
    */

    'storage' => [
        'disk' => env('KNOWLEDGE_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'path_prefix' => 'knowledge',
        'signed_url_ttl_minutes' => (int) env('KNOWLEDGE_SIGNED_URL_TTL', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | `mime_map` is the authority on what may be ingested — the uploaded file's
    | *sniffed* MIME type must appear here and the extension must be listed
    | against it. A file whose extension and detected type disagree is rejected
    | outright rather than being trusted either way.
    |
    */

    'uploads' => [
        'max_file_size_kb' => (int) env('KNOWLEDGE_MAX_FILE_SIZE_KB', 51200), // 50 MB
        'max_files_per_request' => 25,

        'mime_map' => [
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/vnd.ms-powerpoint' => ['ppt'],
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
            'text/plain' => ['txt', 'md', 'markdown'],
            'text/markdown' => ['md', 'markdown'],
            'text/csv' => ['csv'],
            'text/html' => ['html', 'htm'],
            'application/json' => ['json'],
            'application/xml' => ['xml'],
            'text/xml' => ['xml'],
        ],

        // Compressed office/zip containers are expanded during extraction; these
        // caps stop a zip bomb from exhausting worker memory.
        'archive_guard' => [
            'max_entries' => 512,
            'max_uncompressed_bytes' => 256 * 1024 * 1024,
            'max_compression_ratio' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Extraction
    |--------------------------------------------------------------------------
    */

    'extraction' => [
        'timeout_seconds' => (int) env('KNOWLEDGE_EXTRACTION_TIMEOUT', 300),

        // Below this many extracted characters per page a PDF is assumed to be
        // scanned imagery rather than text, and OCR is attempted if enabled.
        'ocr_character_threshold_per_page' => 80,

        'ocr' => [
            'enabled' => (bool) env('KNOWLEDGE_OCR_ENABLED', false),
            'provider' => env('KNOWLEDGE_OCR_PROVIDER', 'null'),
            'max_pages' => (int) env('KNOWLEDGE_OCR_MAX_PAGES', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Sizes are in tokens, estimated at ~4 characters per token. Bounds are hard
    | platform limits; tenant settings are clamped into them.
    |
    */

    'chunking' => [
        'default_strategy' => ChunkingStrategy::Heading->value,
        'default_chunk_size' => 512,
        'default_chunk_overlap' => 64,
        'min_chunk_size' => 64,
        'max_chunk_size' => 2048,
        'max_overlap_ratio' => 0.5,
        'characters_per_token' => 4,
    ],

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | The `local` provider is a deterministic, dependency-free hashed-token
    | embedder. It ships as the default so the module is fully functional out of
    | the box (and in CI) with no API key, but it is NOT semantically strong —
    | it deliberately provides deterministic token similarity rather than
    | generated or intelligent behavior. Model changes require re-indexing.
    |
    */

    'embeddings' => [
        'default_provider' => env('KNOWLEDGE_EMBEDDING_PROVIDER', 'local'),
        'batch_size' => (int) env('KNOWLEDGE_EMBEDDING_BATCH', 64),
        'max_retries' => 5,

        'providers' => [
            'local' => [
                'driver' => 'local',
                'model' => 'promptbot-local-hash-v1',
                'dimensions' => 384,
                'cost_per_million_tokens' => 0.0,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector store
    |--------------------------------------------------------------------------
    |
    | `database` keeps float32 vectors in knowledge_chunks.embedding on the
    | tenant's own MySQL database and scores them in PHP over a metadata-filtered
    | candidate set. That is exact (no ANN recall loss) and needs no extra
    | infrastructure, but it is linear in candidate count — `max_candidates`
    | bounds the work per query. Beyond roughly the low hundreds of thousands of
    | chunks per tenant, implement VectorStoreInterface against a dedicated
    | vector database instead.
    |
    */

    'vector_store' => [
        'driver' => env('KNOWLEDGE_VECTOR_DRIVER', 'database'),
        'max_candidates' => (int) env('KNOWLEDGE_VECTOR_MAX_CANDIDATES', 20000),
        'scan_chunk_size' => 2000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    */

    'retrieval' => [
        'default_mode' => RetrievalMode::Hybrid->value,
        'default_top_k' => 5,
        'max_top_k' => 50,
        'default_candidate_pool' => 20,
        'max_candidate_pool' => 200,
        'default_similarity_threshold' => 0.70,
        'default_max_context_tokens' => 8000,
        'max_context_tokens' => 32000,

        // Weights used to fuse semantic and keyword rankings in hybrid mode.
        'hybrid' => [
            'semantic_weight' => 0.7,
            'keyword_weight' => 0.3,
            // Reciprocal-rank-fusion constant; damps the influence of deep ranks.
            'rrf_k' => 60,
        ],

        'reranking' => [
            'enabled_by_default' => true,
            'driver' => env('KNOWLEDGE_RERANKER', 'heuristic'),
        ],

        'log_retention_days' => (int) env('KNOWLEDGE_RETRIEVAL_LOG_RETENTION', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Website crawling
    |--------------------------------------------------------------------------
    |
    | Crawling fetches attacker-influenced URLs from inside our network, so the
    | guards here are security controls, not tuning knobs. See
    | App\Services\Knowledge\Crawler\UrlSafetyGuard.
    |
    */

    'crawler' => [
        'user_agent' => env('KNOWLEDGE_CRAWLER_UA', 'PromptBotKnowledgeBot/1.0 (+https://promptbot.app/bot)'),
        'default_max_pages' => 200,
        'max_pages_limit' => 5000,
        'default_max_depth' => 3,
        'max_depth_limit' => 10,
        'request_timeout' => 20,
        'delay_between_requests_ms' => 250,
        'max_response_bytes' => 5 * 1024 * 1024,
        'max_redirects' => 3,
        'respect_robots_by_default' => true,

        // Query parameters stripped before URL de-duplication so tracking links
        // do not each become a separate "page".
        'strip_query_parameters' => [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'fbclid', 'mc_cid', 'mc_eid', 'ref', 'source', '_ga',
        ],

        'security' => [
            // Only these schemes may ever be fetched.
            'allowed_schemes' => ['http', 'https'],
            // Hostnames that are always refused regardless of DNS resolution.
            'blocked_hosts' => [
                'localhost', 'localhost.localdomain', 'metadata.google.internal',
                'metadata.goog', 'instance-data', 'kubernetes.default.svc',
            ],
            // CIDR ranges refused after DNS resolution (SSRF / cloud metadata).
            'blocked_cidrs' => [
                '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
                '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
                '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24',
                '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4', '255.255.255.255/32',
                '::1/128', 'fc00::/7', 'fe80::/10', '::ffff:0:0/96',
            ],
            'allow_private_networks' => (bool) env('KNOWLEDGE_CRAWLER_ALLOW_PRIVATE', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Separate queues keep a user clicking "re-index this FAQ" from waiting
    | behind a 5,000-page crawl. Point workers at them with, for example:
    |   php artisan queue:work --queue=knowledge-high,knowledge-processing,...
    |
    */

    'queues' => [
        'high' => env('KNOWLEDGE_QUEUE_HIGH', 'knowledge-high'),
        'processing' => env('KNOWLEDGE_QUEUE_PROCESSING', 'knowledge-processing'),
        'embedding' => env('KNOWLEDGE_QUEUE_EMBEDDING', 'knowledge-embedding'),
        'crawl' => env('KNOWLEDGE_QUEUE_CRAWL', 'knowledge-crawl'),
        'sync' => env('KNOWLEDGE_QUEUE_SYNC', 'knowledge-sync'),
        'low' => env('KNOWLEDGE_QUEUE_LOW', 'knowledge-low'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing
    |--------------------------------------------------------------------------
    */

    'processing' => [
        'max_attempts' => 3,
        // Exponential backoff in seconds between attempts of a transient failure.
        'backoff' => [60, 300, 900],
        'job_timeout' => 900,
        'stale_job_after_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Freshness
    |--------------------------------------------------------------------------
    */

    'freshness' => [
        'default_review_every_days' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tenant usage limits
    |--------------------------------------------------------------------------
    |
    | Fallbacks used when the tenant's subscription plan does not declare a
    | knowledge feature limit. `null` means unlimited. Resolved through
    | App\Services\SaaS\TenantFeatureService where a plan feature exists.
    |
    */

    'limits' => [
        'knowledge_bases' => (int) env('KNOWLEDGE_LIMIT_BASES', 25),
        'sources_per_base' => (int) env('KNOWLEDGE_LIMIT_SOURCES', 2000),
        'documents' => (int) env('KNOWLEDGE_LIMIT_DOCUMENTS', 10000),
        'website_pages' => (int) env('KNOWLEDGE_LIMIT_PAGES', 20000),
        'chunks' => (int) env('KNOWLEDGE_LIMIT_CHUNKS', 500000),
        'storage_bytes' => (int) env('KNOWLEDGE_LIMIT_STORAGE_BYTES', 10 * 1024 * 1024 * 1024),
        'embedding_tokens_per_month' => (int) env('KNOWLEDGE_LIMIT_EMBED_TOKENS', 50000000),
        'crawl_pages_per_month' => (int) env('KNOWLEDGE_LIMIT_CRAWL_PAGES', 100000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limits (per tenant, per minute unless noted)
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'uploads_per_minute' => 60,
        'playground_per_minute' => 30,
        'retrieval_api_per_minute' => 120,
        'reindex_per_hour' => 20,
        'manual_sync_per_hour' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Languages offered in the UI
    |--------------------------------------------------------------------------
    */

    'languages' => [
        'en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'de' => 'German',
        'pt' => 'Portuguese', 'it' => 'Italian', 'nl' => 'Dutch', 'ar' => 'Arabic',
        'hi' => 'Hindi', 'ne' => 'Nepali', 'zh' => 'Chinese', 'ja' => 'Japanese',
        'ko' => 'Korean', 'ru' => 'Russian', 'tr' => 'Turkish', 'id' => 'Indonesian',
    ],
];
