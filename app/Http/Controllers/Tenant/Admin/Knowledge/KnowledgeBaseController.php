<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\ChunkingStrategy;
use App\Enums\Knowledge\KnowledgeBaseStatus;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\RetrievalMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\KnowledgeBaseRequest;
use App\Jobs\Knowledge\PurgeKnowledgeResourceJob;
use App\Jobs\Knowledge\ReindexKnowledgeBaseJob;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\Knowledge\Embedding\EmbeddingProviderFactory;
use App\Services\Knowledge\KnowledgeAnalyticsService;
use App\Services\Knowledge\KnowledgeBaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeBaseController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly KnowledgeBaseService $bases,
        private readonly EmbeddingProviderFactory $providers,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeBase::class);

        $query = KnowledgeBase::query()
            ->with(['creator:id,name'])
            ->withCount('accessGrants')
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('language'), fn ($q) => $q->where('default_language', $request->string('language')))
            ->when($request->filled('owner'), fn ($q) => $q->where('created_by', $request->integer('owner')));

        $this->scopeToAllowedBases($query, 'id');

        return Inertia::render('Tenant/Admin/Knowledge/Bases/Index', [
            'bases' => $query->orderByDesc('updated_at')->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'language', 'owner']),
            'statuses' => $this->enumOptions(KnowledgeBaseStatus::cases()),
            'languages' => config('knowledge.languages'),
            'can' => ['create' => Gate::allows('create', KnowledgeBase::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', KnowledgeBase::class);

        return Inertia::render('Tenant/Admin/Knowledge/Bases/Create', $this->formOptions());
    }

    public function store(KnowledgeBaseRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeBase::class);

        $base = $this->bases->create($request->validated(), $request->user('tenant'));

        return redirect()
            ->route('tenant.admin.knowledge.bases.show', $base->uuid)
            ->with('status', 'Knowledge base created. Add your first source to give your agents something to work with.');
    }

    public function show(Request $request, string $knowledgeBase, KnowledgeAnalyticsService $analytics): Response
    {
        $base = $this->resolveBase($knowledgeBase);
        Gate::authorize('view', $base);

        return Inertia::render('Tenant/Admin/Knowledge/Bases/Show', [
            'base' => $base->load(['creator:id,name', 'editor:id,name']),
            'collections' => $base->collections()->orderBy('sort_order')->orderBy('name')->get(),
            'sources' => $base->sources()
                ->with('collection:id,name')
                ->orderByDesc('updated_at')
                ->limit(25)
                ->get(),
            'grants' => $base->accessGrants()->get(),
            'analytics' => $analytics->analytics([$base->id], 30),
            'coverage' => $this->coverage($base),
            'can' => [
                'update' => Gate::allows('update', $base),
                'delete' => Gate::allows('delete', $base),
                'managePermissions' => Gate::allows('managePermissions', $base),
                'manageSettings' => Gate::allows('manageSettings', $base),
                'testRetrieval' => Gate::allows('testRetrieval', $base),
                'reindex' => Gate::allows('reindex', $base),
                'viewAnalytics' => Gate::allows('viewAnalytics', $base),
            ],
        ]);
    }

    public function edit(string $knowledgeBase): Response
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('update', $base);

        return Inertia::render('Tenant/Admin/Knowledge/Bases/Edit', array_merge($this->formOptions(), [
            'base' => $base,
            'staleVectorCount' => app(\App\Contracts\Knowledge\VectorStoreInterface::class)
                ->countStaleVectors($base->id, $base->embeddingSignature()),
        ]));
    }

    public function update(KnowledgeBaseRequest $request, string $knowledgeBase): RedirectResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('update', $base);

        $result = $this->bases->update($base, $request->validated(), $request->user('tenant'));

        // Changing the embedding model invalidates every vector, so the
        // re-index is kicked off here rather than left for the operator to
        // remember — a base silently returning nothing is the worse failure.
        if ($result['requires_reindex']) {
            ReindexKnowledgeBaseJob::dispatch($base->id);

            return back()->with('status', 'Settings saved. The embedding model changed, so this knowledge base is being re-indexed — retrieval will be incomplete until that finishes.');
        }

        return back()->with('status', 'Knowledge base updated.');
    }

    public function destroy(Request $request, string $knowledgeBase): RedirectResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('delete', $base);

        $purge = $request->boolean('purge');

        if ($purge) {
            Gate::authorize('forceDelete', $base);
        }

        $this->bases->delete($base, $request->user('tenant'));

        if ($purge) {
            PurgeKnowledgeResourceJob::dispatch('knowledge_base', $base->id);
        }

        return redirect()
            ->route('tenant.admin.knowledge.bases.index')
            ->with('status', $purge
                ? 'Knowledge base deleted. Its files and chunks are being permanently removed.'
                : 'Knowledge base deleted and removed from retrieval.');
    }

    public function archive(Request $request, string $knowledgeBase): RedirectResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('update', $base);

        $this->bases->archive($base, $request->user('tenant'));

        return back()->with('status', 'Knowledge base archived. Its content no longer answers questions.');
    }

    public function restore(Request $request, string $knowledgeBase): RedirectResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('update', $base);

        $this->bases->restore($base, $request->user('tenant'));

        return back()->with('status', 'Knowledge base restored.');
    }

    public function reindex(Request $request, string $knowledgeBase): RedirectResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('reindex', $base);

        $rechunk = $request->boolean('rechunk');

        ReindexKnowledgeBaseJob::dispatch($base->id, $rechunk);

        return back()->with('status', $rechunk
            ? 'Re-processing every document in this knowledge base. Existing answers keep working until the new index is ready.'
            : 'Rebuilding embeddings for this knowledge base.');
    }

    /** What the deletion dialog needs in order to state the consequences. */
    public function impact(string $knowledgeBase): \Illuminate\Http\JsonResponse
    {
        $base = $this->resolveBase($knowledgeBase, AccessLevel::Manage);
        Gate::authorize('delete', $base);

        return response()->json($this->bases->deletionImpact($base));
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'languages' => config('knowledge.languages'),
            'visibilities' => $this->enumOptions(KnowledgeVisibility::cases()),
            // Processing/Warning/Archived are excluded: the first two are
            // computed by the statistics reconciler, and Archived has its own
            // dedicated action — none of them belong in a plain dropdown.
            'statuses' => $this->enumOptions([
                KnowledgeBaseStatus::Draft, KnowledgeBaseStatus::Active, KnowledgeBaseStatus::Disabled,
            ]),
            'chunkingStrategies' => array_map(fn (ChunkingStrategy $c) => [
                'value' => $c->value, 'label' => $c->label(), 'description' => $c->description(),
            ], ChunkingStrategy::cases()),
            'retrievalModes' => array_map(fn (RetrievalMode $m) => [
                'value' => $m->value, 'label' => $m->label(), 'description' => $m->description(),
            ], RetrievalMode::cases()),
            'embeddingProviders' => $this->providers->catalogue(),
            'defaults' => [
                'chunk_size' => config('knowledge.chunking.default_chunk_size'),
                'chunk_overlap' => config('knowledge.chunking.default_chunk_overlap'),
                'top_k' => config('knowledge.retrieval.default_top_k'),
                'candidate_pool' => config('knowledge.retrieval.default_candidate_pool'),
                'similarity_threshold' => config('knowledge.retrieval.default_similarity_threshold'),
                'max_context_tokens' => config('knowledge.retrieval.default_max_context_tokens'),
            ],
            'teams' => \App\Models\Team::query()->orderBy('name')->get(['id', 'name']),
            'roles' => \App\Models\TenantRole::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * Knowledge coverage by tag — a first, honest approximation of "what topics
     * does this base actually cover". It reports what has been tagged, and does
     * not infer topics it cannot see.
     *
     * @return array<int, array{label: string, documents: int}>
     */
    private function coverage(KnowledgeBase $base): array
    {
        return \Illuminate\Support\Facades\DB::table('knowledge_taggables')
            ->join('knowledge_tags', 'knowledge_tags.id', '=', 'knowledge_taggables.knowledge_tag_id')
            ->join('knowledge_sources', function ($join): void {
                $join->on('knowledge_sources.id', '=', 'knowledge_taggables.taggable_id')
                    ->where('knowledge_taggables.taggable_type', '=', \App\Models\Knowledge\KnowledgeSource::class);
            })
            ->where('knowledge_sources.knowledge_base_id', $base->id)
            ->selectRaw('knowledge_tags.name as label, sum(knowledge_sources.document_count) as documents')
            ->groupBy('knowledge_tags.name')
            ->orderByDesc('documents')
            ->limit(12)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'documents' => (int) $row->documents])
            ->all();
    }

    /** @param  array<int, \BackedEnum>  $cases */
    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : $case->value,
        ], $cases);
    }
}
