<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Jobs\Knowledge\SyncKnowledgeSourceJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Models\Knowledge\KnowledgeSyncRun;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\KnowledgeSyncService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SourceController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly KnowledgeIndexService $index,
        private readonly KnowledgeSyncService $sync,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeSource::class);

        $query = KnowledgeSource::query()
            ->with(['knowledgeBase:id,uuid,name', 'collection:id,name', 'creator:id,name', 'tags:id,name,slug'])
            ->when($request->filled('type'), fn ($q) => $q->where('source_type', $request->string('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Sources/Index', [
            'sources' => $query->orderByDesc('updated_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['type', 'status', 'search', 'knowledge_base']),
            'bases' => $this->selectableBases(),
            'types' => array_map(fn (SourceType $t) => ['value' => $t->value, 'label' => $t->label()], SourceType::cases()),
            'statuses' => array_map(fn (SourceStatus $s) => ['value' => $s->value, 'label' => $s->label()], SourceStatus::cases()),
            'can' => ['create' => Gate::allows('create', KnowledgeSource::class)],
        ]);
    }

    /**
     * The source detail page answers the operator's questions in one place:
     * what is this, where did it come from, is it healthy, when did it last
     * sync, how much knowledge did it produce, and who can reach it.
     */
    public function show(string $source): Response
    {
        $record = $this->resolveSource($source);
        Gate::authorize('view', $record);

        return Inertia::render('Tenant/Admin/Knowledge/Sources/Show', [
            'source' => $record->load(['knowledgeBase:id,uuid,name', 'collection:id,name', 'creator:id,name', 'tags:id,name,slug']),
            'freshness' => $record->freshness()->value,
            'documents' => $record->documents()
                ->summaryColumns()
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(),
            'pages' => $record->source_type->isCrawlable()
                ? $record->websitePages()->orderByDesc('last_crawled_at')->limit(50)->get()
                : [],
            'syncRuns' => $record->syncRuns()->limit(20)->get(),
            'failures' => $record->failures()->limit(20)->get(),
            // Never the credential itself — only its provider, account label and
            // validation state.
            'credential' => $record->credential?->summary(),
            'can' => [
                'update' => Gate::allows('update', $record),
                'delete' => Gate::allows('delete', $record),
                'sync' => Gate::allows('sync', $record),
                'reindex' => Gate::allows('reindex', $record),
                'manageCredentials' => Gate::allows('manageCredentials', $record),
            ],
        ]);
    }

    public function sync(Request $request, string $source): RedirectResponse
    {
        $record = $this->resolveSource($source);
        Gate::authorize('sync', $record);

        if (! $record->source_type->isSyncable()) {
            return back()->with('error', 'This source type holds content uploaded directly to PromptBot — there is nothing remote to synchronise.');
        }

        $record->forceFill(['sync_status' => \App\Enums\Knowledge\SyncStatus::Queued->value])->save();

        SyncKnowledgeSourceJob::dispatch($record->id, KnowledgeSyncRun::TRIGGER_MANUAL, $request->user('tenant')?->id);

        $this->auditLog->record('knowledge.sync_triggered', $request->user('tenant'), "Triggered sync for \"{$record->name}\"", $record);

        return back()->with('status', 'Synchronisation queued.');
    }

    public function disable(Request $request, string $source): RedirectResponse
    {
        $record = $this->resolveSource($source);
        Gate::authorize('update', $record);

        $record->forceFill([
            'status' => SourceStatus::Disabled->value,
            'next_sync_at' => null,
        ])->save();

        // Disabling must take effect for retrieval right away, not at the next
        // index run.
        $this->index->withdrawSource($record);

        $this->auditLog->record('knowledge.source_disabled', $request->user('tenant'), "Disabled \"{$record->name}\"", $record);

        return back()->with('status', 'Source disabled and removed from retrieval.');
    }

    public function enable(Request $request, string $source): RedirectResponse
    {
        $record = $this->resolveSource($source);
        Gate::authorize('update', $record);

        $record->forceFill(['status' => SourceStatus::Ready->value])->save();
        $this->index->restoreSource($record);
        $this->sync->scheduleNextRun($record);

        return back()->with('status', 'Source enabled.');
    }

    public function destroy(Request $request, string $source): RedirectResponse
    {
        $record = $this->resolveSource($source);
        Gate::authorize('delete', $record);

        $name = $record->name;

        $this->index->withdrawSource($record);
        $record->delete();

        $this->auditLog->record(
            'knowledge.source_deleted',
            $request->user('tenant'),
            "Deleted source \"{$name}\"",
            subjectType: KnowledgeSource::class,
            subjectLabel: $name,
            metadata: ['uuid' => $record->uuid, 'documents' => $record->document_count, 'chunks' => $record->chunk_count],
        );

        return redirect()
            ->route('tenant.admin.knowledge.sources.index')
            ->with('status', "\"{$name}\" was deleted and is no longer used for answers.");
    }

    private function resolveSource(string $uuid): KnowledgeSource
    {
        $source = KnowledgeSource::query()->where('uuid', $uuid)->first();

        if (! $source || ! in_array($source->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        return $source;
    }
}
