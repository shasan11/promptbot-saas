<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Enums\Knowledge\SyncFrequency;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\WebsiteSourceRequest;
use App\Jobs\Knowledge\SyncKnowledgeSourceJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeWebsitePage;
use App\Services\Knowledge\KnowledgeSyncService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly KnowledgeSyncService $sync,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeSource::class);

        $sources = KnowledgeSource::query()
            ->with(['knowledgeBase:id,uuid,name', 'collection:id,name'])
            ->whereIn('source_type', [SourceType::Website->value, SourceType::WebsiteCrawl->value, SourceType::Sitemap->value])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')));

        $this->scopeToAllowedBases($sources);

        $pages = KnowledgeWebsitePage::query()
            ->with(['source:id,uuid,name'])
            ->when($request->filled('source'), function ($q) use ($request): void {
                $q->whereHas('source', fn ($s) => $s->where('uuid', $request->string('source')));
            })
            ->when($request->filled('page_status'), fn ($q) => $q->where('status', $request->string('page_status')));

        $this->scopeToAllowedBases($pages);

        return Inertia::render('Tenant/Admin/Knowledge/Websites/Index', [
            'sources' => $sources->orderByDesc('updated_at')->get(),
            'pages' => $pages->orderByDesc('last_crawled_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['status', 'source', 'page_status']),
            'bases' => $this->selectableBases(AccessLevel::Contribute),
            'syncFrequencies' => array_map(
                fn (SyncFrequency $f) => ['value' => $f->value, 'label' => $f->label()],
                SyncFrequency::cases()
            ),
            'crawlLimits' => [
                'max_pages' => config('knowledge.crawler.max_pages_limit'),
                'max_depth' => config('knowledge.crawler.max_depth_limit'),
                'default_max_pages' => config('knowledge.crawler.default_max_pages'),
                'default_max_depth' => config('knowledge.crawler.default_max_depth'),
            ],
            'can' => ['create' => Gate::allows('create', KnowledgeSource::class)],
        ]);
    }

    public function store(WebsiteSourceRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeSource::class);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), AccessLevel::Contribute);
        $type = SourceType::from($request->string('source_type')->toString());
        $url = $request->string('url')->toString();

        $source = KnowledgeSource::create([
            'knowledge_base_id' => $base->id,
            'knowledge_collection_id' => $request->integer('collection_id') ?: null,
            'source_type' => $type->value,
            'name' => $request->string('name')->toString() ?: parse_url($url, PHP_URL_HOST) ?: $url,
            'status' => SourceStatus::Pending->value,
            'sync_frequency' => $request->string('sync_frequency')->toString() ?: SyncFrequency::Weekly->value,
            'configuration' => [
                'url' => $url,
                'crawl' => array_merge([
                    // A single-URL source is a crawl with a budget of one page
                    // and no link following — the same code path, configured
                    // differently, rather than a second implementation.
                    'max_pages' => $type === SourceType::Website ? 1 : (int) config('knowledge.crawler.default_max_pages'),
                    'max_depth' => $type === SourceType::Website ? 0 : (int) config('knowledge.crawler.default_max_depth'),
                    'use_sitemap' => $type === SourceType::Sitemap,
                ], $request->input('crawl', [])),
            ],
            'created_by' => $request->user('tenant')?->id,
        ]);

        $this->sync->scheduleNextRun($source);

        SyncKnowledgeSourceJob::dispatch($source->id, \App\Models\Knowledge\KnowledgeSyncRun::TRIGGER_MANUAL, $request->user('tenant')?->id);

        $this->auditLog->record(
            'knowledge.source_added',
            $request->user('tenant'),
            "Added website source \"{$source->name}\" to \"{$base->name}\"",
            $source,
            newValues: ['url' => $url, 'type' => $type->value],
        );

        return back()->with('status', $type === SourceType::Website
            ? 'Page queued for indexing.'
            : 'Crawl started. Pages appear here as they are discovered.');
    }
}
