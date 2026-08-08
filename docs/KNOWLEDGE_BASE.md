# Knowledge Base Module

PromptBot's Knowledge Base module is the tenant-local RAG layer used by agents, support workflows, automations, and admins.

## Architecture

- Tenant isolation is database-per-tenant through `stancl/tenancy`; knowledge rows live in tenant migrations under `database/migrations/tenant`.
- Knowledge bases are containers. Sources describe where knowledge came from. Documents, website pages, FAQs, and manual text hold source content. Chunks are the retrieval units.
- Retrieval is always scoped before search through `App\Services\Knowledge\KnowledgePermissionService`.
- Vectors are stored by `App\Contracts\Knowledge\VectorStoreInterface`; the shipped implementation is MySQL-backed exact search.
- Embeddings are provided by `App\Contracts\Knowledge\EmbeddingProviderInterface`; local hash embeddings are available for development and OpenAI embeddings can be enabled by configuration.

## Main Flows

1. Tenant admin creates a knowledge base.
2. Admin adds content through uploaded documents, manual text, FAQs, or website sources.
3. Content enters queued processing jobs.
4. The pipeline extracts or reads text, normalizes it, detects language, chunks it, and queues embeddings.
5. Embedded chunks become retrievable.
6. Retrieval playground and agent callers search through permission-filtered semantic, keyword, or hybrid retrieval.
7. Retrieval logs, usage records, gaps, failures, and processing logs support analytics and operations.

## HTTP Surfaces

Tenant admin routes are under `/knowledge`:

- `/knowledge` overview
- `/knowledge/bases`
- `/knowledge/documents`
- `/knowledge/websites`
- `/knowledge/faqs`
- `/knowledge/text-sources`
- `/knowledge/collections`
- `/knowledge/sources`
- `/knowledge/processing`
- `/knowledge/failed`
- `/knowledge/playground`
- `/knowledge/analytics`
- `/knowledge/settings`

Controller endpoints return Inertia pages except `/knowledge/playground/retrieve`, which returns JSON retrieval results and optional debug data for users with `knowledge.manage`.

## Retrieval JSON Contract

`POST /knowledge/playground/retrieve`

Request fields:

- `query` string, required
- `knowledge_bases` array of knowledge base UUIDs, optional and always narrowed to the actor's allowed bases
- `collection_ids` array of collection IDs, optional and always narrowed to the actor's allowed collections
- `source_ids` array of source IDs, optional
- `tags` array of tag slugs, optional
- `language` language code, optional
- `mode` one of `semantic`, `keyword`, or `hybrid`
- `top_k` integer
- `similarity_threshold` number from 0 to 1
- `rerank` boolean
- `debug` boolean, honored only for users with `knowledge.manage`

Response fields:

- `query`
- `results` with chunk text, rank, scores, source metadata, and citation data
- `citations`
- `timings`
- `context_tokens`
- `zero_results`
- `log_uuid`
- `debug`, only when authorized

This endpoint is for administrator testing. Production agent calls should create a `RetrievalQuery` server-side from the agent's explicit grant scope rather than accepting client-provided base IDs.

## Queue Workers

Run queue workers for the configured knowledge queues:

```bash
php artisan queue:work --queue=knowledge-processing,knowledge-embedding,knowledge-crawl,knowledge-low
```

Exact queue names are controlled in `config/knowledge.php`.

## Scheduled Sync

Knowledge source synchronization is registered through the Laravel scheduler. Ensure the scheduler is running in production:

```bash
php artisan schedule:run
```

## Configuration

Important settings live in `config/knowledge.php`:

- allowed document MIME types and sizes
- storage disk/path
- chunking defaults and bounds
- retrieval defaults and rate limits
- embedding providers
- crawler safety, robots, sitemap, depth, and page limits
- OCR provider enablement

## Security Notes

- Tenant boundaries are enforced by separate tenant databases.
- Within a tenant, knowledge base access is scoped by permissions and grants before retrieval.
- AI agents never receive access from workspace visibility alone; they require explicit agent grants.
- Website crawling is protected by `UrlSafetyGuard`, private-network blocking, URL normalization, redirect handling, robots.txt, and page/depth limits.
- Signed document downloads still re-check the document policy.
- Failure retries create a new `knowledge_processing_jobs` row before dispatching work so operators can see the retry in the Processing queue.
- Retrieved content is treated as untrusted data and fenced by the retrieval context builder.

## Testing

Useful verification commands:

```bash
php artisan test tests/Unit/Knowledge --display-errors
php artisan test tests/Feature/Tenant/Knowledge/KnowledgeModuleTest.php --display-errors
php artisan route:cache
npm.cmd run build
```

Feature tests provision real tenant databases, so they are slower than the unit suite.
