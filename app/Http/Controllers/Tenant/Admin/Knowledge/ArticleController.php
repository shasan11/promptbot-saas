<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\ArticleStatus;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\ArticleRequest;
use App\Jobs\Knowledge\EmbedKnowledgeBaseJob;
use App\Models\Knowledge\KnowledgeArticle;
use App\Models\Knowledge\KnowledgeArticleVersion;
use App\Models\Knowledge\KnowledgeChunk;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\KnowledgeVersionService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ArticleController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly KnowledgeIndexService $index,
        private readonly KnowledgeVersionService $versions,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeArticle::class);

        $query = KnowledgeArticle::query()
            ->with(['knowledgeBase:id,uuid,name', 'collection:id,name', 'author:id,name', 'reviewer:id,name'])
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Articles/Index', [
            'articles' => $query->orderByDesc('updated_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'knowledge_base']),
            'bases' => $this->selectableBases(AccessLevel::Contribute),
            'statuses' => array_map(fn (ArticleStatus $s) => ['value' => $s->value, 'label' => $s->label()], ArticleStatus::cases()),
            'languages' => config('knowledge.languages'),
            'can' => ['create' => Gate::allows('create', KnowledgeArticle::class)],
        ]);
    }

    /** Every article currently awaiting a reviewer's decision, across every base the actor can reach. */
    public function reviewQueue(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeArticle::class);

        $query = KnowledgeArticle::query()
            ->with(['knowledgeBase:id,uuid,name', 'author:id,name'])
            ->where('status', ArticleStatus::InReview->value);

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Articles/ReviewQueue', [
            'articles' => $query->orderBy('review_requested_at')->paginate(25)->withQueryString(),
            // Reviewing is gated by the same permission for every article the
            // actor can already reach (the query above already scoped that),
            // so a single flag is enough — no need to evaluate per row.
            'can' => ['approve' => $request->user('tenant')?->can('knowledge.update') ?? false],
        ]);
    }

    public function store(ArticleRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeArticle::class);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), AccessLevel::Contribute);
        $source = $this->articleSourceFor($base->id, $request->user('tenant')?->id);

        $article = KnowledgeArticle::create(array_merge($request->safe()->except(['knowledge_base', 'tags', 'collection_id']), [
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $request->integer('collection_id') ?: null,
            'slug' => $this->uniqueSlug($base->id, $request->string('slug')->toString() ?: $request->string('title')->toString()),
            'language' => $request->string('language')->toString() ?: $base->default_language,
            'status' => ArticleStatus::Draft->value,
            'allow_ai_access' => $request->boolean('allow_ai_access', true),
            'author_id' => $request->user('tenant')?->id,
            'created_by' => $request->user('tenant')?->id,
            'updated_by' => $request->user('tenant')?->id,
        ]));

        if ($tags = $request->input('tags', [])) {
            $article->syncTagNames($tags);
        }

        $this->auditLog->record('knowledge.article_created', $request->user('tenant'), 'Created a knowledge article', $article);

        return back()->with('status', 'Article saved as a draft. Submit it for review when it is ready.');
    }

    public function update(ArticleRequest $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('update', $record);

        // Snapshot before overwriting, so the previous wording is recoverable.
        $this->versions->snapshotArticle($record, 'Edited', $request->user('tenant'));

        $record->update(array_merge($request->safe()->except(['knowledge_base', 'tags', 'slug']), [
            'updated_by' => $request->user('tenant')?->id,
        ]));

        if ($request->has('tags')) {
            $record->syncTagNames($request->input('tags', []));
        }

        $this->reindex($record);

        $this->auditLog->record('knowledge.article_updated', $request->user('tenant'), 'Updated a knowledge article', $record);

        return back()->with('status', 'Article updated.');
    }

    public function submitForReview(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('submitForReview', $record);

        $this->transition($record, ArticleStatus::InReview, [
            'review_requested_at' => now(),
            'review_note' => null,
        ]);

        return back()->with('status', 'Submitted for review. It will not answer questions until it is approved.');
    }

    public function approve(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('approve', $record);

        $this->versions->snapshotArticle($record, 'Approved and published', $request->user('tenant'));

        $this->transition($record, ArticleStatus::Published, [
            'reviewer_id' => $request->user('tenant')?->id,
            'reviewed_at' => now(),
            'published_by' => $request->user('tenant')?->id,
            'published_at' => now(),
        ]);

        $this->auditLog->record('knowledge.article_published', $request->user('tenant'), 'Approved and published a knowledge article', $record);

        return back()->with('status', 'Article published — your agents can use it now.');
    }

    public function reject(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('reject', $record);

        $request->validate(['review_note' => ['required', 'string', 'max:2000']]);

        $this->transition($record, ArticleStatus::Draft, [
            'reviewer_id' => $request->user('tenant')?->id,
            'reviewed_at' => now(),
            'review_note' => $request->string('review_note')->toString(),
        ]);

        return back()->with('status', 'Sent back to the author with your notes.');
    }

    public function archive(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('archive', $record);

        $this->transition($record, ArticleStatus::Archived, ['archived_at' => now()]);

        $this->auditLog->record('knowledge.article_archived', $request->user('tenant'), 'Archived a knowledge article', $record);

        return back()->with('status', 'Article archived and removed from retrieval.');
    }

    public function restore(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('restore', $record);

        // Restoring only reopens the article for editing — it must be
        // resubmitted and re-approved before it can answer questions again.
        $this->transition($record, ArticleStatus::Draft, ['archived_at' => null]);

        return back()->with('status', 'Article restored as a draft.');
    }

    /** JSON, not a page — the Articles index opens this in a "Versions" panel rather than navigating away. */
    public function versions(string $article): \Illuminate\Http\JsonResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('view', $record);

        return response()->json([
            'versions' => $record->versions()->with('author:id,name')->get()
                ->map(fn (KnowledgeArticleVersion $v) => [
                    'version_number' => $v->version_number,
                    'title' => $v->title,
                    'summary' => $v->summary,
                    'status' => $v->status,
                    'change_summary' => $v->change_summary,
                    'author' => $v->author?->name,
                    'created_at' => $v->created_at,
                ]),
        ]);
    }

    public function restoreVersion(Request $request, string $article, int $version): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('update', $record);

        $versionRecord = KnowledgeArticleVersion::query()
            ->where('knowledge_article_id', $record->id)
            ->where('version_number', $version)
            ->firstOrFail();

        $this->versions->restoreArticle($record, $versionRecord, $request->user('tenant'));
        $this->reindex($record->refresh());

        return back()->with('status', "Restored version {$version}.");
    }

    public function destroy(Request $request, string $article): RedirectResponse
    {
        $record = $this->resolveArticle($article);
        Gate::authorize('delete', $record);

        $title = $record->title;

        KnowledgeChunk::query()->where('owner_key', $record->chunkOwnerKey())->delete();
        $record->delete();

        $this->auditLog->record(
            'knowledge.article_deleted',
            $request->user('tenant'),
            'Deleted a knowledge article',
            subjectType: KnowledgeArticle::class,
            subjectLabel: Str::limit($title, 80),
        );

        return back()->with('status', 'Article deleted.');
    }

    /**
     * Applies a guarded status transition and reindexes. Guarding here — not
     * just in the policy — means a stray double-click that fires two
     * "approve" requests cannot push an already-published article through an
     * invalid transition.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(KnowledgeArticle $article, ArticleStatus $target, array $attributes): void
    {
        abort_unless($article->status->canTransitionTo($target), 422, "Cannot move an article from {$article->status->label()} to {$target->label()}.");

        $article->update(array_merge($attributes, ['status' => $target->value]));

        $this->reindex($article);
    }

    private function reindex(KnowledgeArticle $article): void
    {
        $this->index->syncArticleChunks($article->refresh());

        if ($article->isRetrievable()) {
            EmbedKnowledgeBaseJob::dispatch($article->knowledge_base_id);
        }
    }

    private function resolveArticle(string $uuid): KnowledgeArticle
    {
        $article = KnowledgeArticle::query()->where('uuid', $uuid)->first();

        if (! $article || ! in_array($article->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        return $article;
    }

    private function articleSourceFor(int $baseId, ?int $actorId): KnowledgeSource
    {
        return KnowledgeSource::firstOrCreate(
            ['knowledge_base_id' => $baseId, 'source_type' => SourceType::Article->value, 'knowledge_collection_id' => null],
            [
                'name' => 'Articles',
                'description' => 'Authored, reviewed knowledge maintained in PromptBot.',
                'status' => SourceStatus::Ready->value,
                'created_by' => $actorId,
            ]
        );
    }

    private function uniqueSlug(int $baseId, string $title): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $suffix = 1;

        while (KnowledgeArticle::query()->where('knowledge_base_id', $baseId)->where('slug', $slug)->exists()) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
    }
}
