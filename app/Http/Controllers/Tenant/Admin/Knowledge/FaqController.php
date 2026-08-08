<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\FaqStatus;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\FaqRequest;
use App\Jobs\Knowledge\EmbedKnowledgeBaseJob;
use App\Models\Knowledge\KnowledgeFaq;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\KnowledgeVersionService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FaqController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly KnowledgeIndexService $index,
        private readonly KnowledgeVersionService $versions,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeFaq::class);

        $query = KnowledgeFaq::query()
            ->with(['knowledgeBase:id,uuid,name', 'collection:id,name', 'author:id,name'])
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        $categories = KnowledgeFaq::query()
            ->whereIn('knowledge_base_id', $this->allowedBaseIds() ?: [0])
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('Tenant/Admin/Knowledge/Faqs/Index', [
            'faqs' => $query->orderByDesc('priority')->orderByDesc('updated_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'category', 'knowledge_base']),
            'bases' => $this->selectableBases(AccessLevel::Contribute),
            'categories' => $categories,
            'statuses' => array_map(fn (FaqStatus $s) => ['value' => $s->value, 'label' => $s->label()], FaqStatus::cases()),
            'languages' => config('knowledge.languages'),
            'can' => ['create' => Gate::allows('create', KnowledgeFaq::class)],
        ]);
    }

    public function store(FaqRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeFaq::class);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), AccessLevel::Contribute);
        $source = $this->faqSourceFor($base->id, $request->user('tenant')?->id);

        $faq = KnowledgeFaq::create(array_merge($request->safe()->except(['knowledge_base', 'tags', 'collection_id']), [
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $request->integer('collection_id') ?: null,
            'language' => $request->string('language')->toString() ?: $base->default_language,
            'status' => $request->string('status')->toString() ?: FaqStatus::Draft->value,
            'created_by' => $request->user('tenant')?->id,
            'updated_by' => $request->user('tenant')?->id,
        ]));

        if ($tags = $request->input('tags', [])) {
            $faq->syncTagNames($tags);
        }

        $this->reindex($faq);

        $this->auditLog->record('knowledge.faq_created', $request->user('tenant'), 'Created an FAQ', $faq);

        return back()->with('status', $faq->status->isRetrievable()
            ? 'FAQ published and queued for indexing.'
            : 'FAQ saved as a draft. Publish it to make it available to your agents.');
    }

    public function update(FaqRequest $request, string $faq): RedirectResponse
    {
        $record = $this->resolveFaq($faq);
        Gate::authorize('update', $record);

        $wasPublished = $record->status->isRetrievable();
        $publishing = ($request->string('status')->toString() ?: $record->status->value) === FaqStatus::Published->value;

        if ($publishing && ! $wasPublished) {
            Gate::authorize('publish', $record);
        }

        // Snapshot before overwriting, so the previous wording is recoverable.
        $this->versions->snapshotFaq($record, 'Edited', $request->user('tenant'));

        $record->update(array_merge($request->safe()->except(['knowledge_base', 'tags']), [
            'updated_by' => $request->user('tenant')?->id,
        ]));

        if ($request->has('tags')) {
            $record->syncTagNames($request->input('tags', []));
        }

        $this->reindex($record);

        $this->auditLog->record('knowledge.faq_updated', $request->user('tenant'), 'Updated an FAQ', $record);

        return back()->with('status', 'FAQ updated.');
    }

    public function publish(Request $request, string $faq): RedirectResponse
    {
        $record = $this->resolveFaq($faq);
        Gate::authorize('publish', $record);

        $publish = $request->boolean('published', true);

        $record->update(['status' => $publish ? FaqStatus::Published : FaqStatus::Draft]);

        // Unpublishing must take the FAQ out of retrieval at once — a withdrawn
        // answer that keeps being given is the failure this guards against.
        $this->reindex($record);

        return back()->with('status', $publish
            ? 'FAQ published — your agents can use it now.'
            : 'FAQ unpublished and removed from retrieval.');
    }

    public function destroy(Request $request, string $faq): RedirectResponse
    {
        $record = $this->resolveFaq($faq);
        Gate::authorize('delete', $record);

        $question = $record->question;

        \App\Models\Knowledge\KnowledgeChunk::query()->where('owner_key', $record->chunkOwnerKey())->delete();
        $record->delete();

        $this->auditLog->record(
            'knowledge.faq_deleted',
            $request->user('tenant'),
            'Deleted an FAQ',
            subjectType: KnowledgeFaq::class,
            subjectLabel: \Illuminate\Support\Str::limit($question, 80),
        );

        return back()->with('status', 'FAQ deleted.');
    }

    /**
     * Bulk CSV import. Rows are validated individually so one malformed line
     * does not discard a 500-row import.
     */
    public function import(Request $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeFaq::class);

        $request->validate([
            'knowledge_base' => ['required', 'string', 'uuid'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), AccessLevel::Contribute);
        $source = $this->faqSourceFor($base->id, $request->user('tenant')?->id);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $header = null;
        $imported = 0;
        $skipped = 0;

        try {
            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                if ($header === null) {
                    $header = array_map(fn ($h) => \Illuminate\Support\Str::snake(trim((string) $h)), $row);

                    continue;
                }

                $data = array_combine(
                    array_slice($header, 0, count($row)),
                    array_slice($row, 0, count($header))
                ) ?: [];

                $question = trim((string) ($data['question'] ?? ''));
                $answer = trim((string) ($data['answer'] ?? ''));

                if ($question === '' || $answer === '') {
                    $skipped++;

                    continue;
                }

                $faq = KnowledgeFaq::create([
                    'knowledge_base_id' => $base->id,
                    'knowledge_source_id' => $source->id,
                    'question' => \Illuminate\Support\Str::limit($question, 1000, ''),
                    'answer' => \Illuminate\Support\Str::limit($answer, 20000, ''),
                    'category' => trim((string) ($data['category'] ?? '')) ?: null,
                    'language' => trim((string) ($data['language'] ?? '')) ?: $base->default_language,
                    // Imported rows land as drafts: a bulk import is exactly
                    // when a human should review before it starts answering
                    // customers.
                    'status' => FaqStatus::Draft->value,
                    'created_by' => $request->user('tenant')?->id,
                ]);

                $this->index->syncFaqChunks($faq);
                $imported++;
            }
        } finally {
            fclose($handle);
        }

        return back()->with('status', "Imported {$imported} FAQ(s) as drafts"
            .($skipped ? ", skipped {$skipped} row(s) missing a question or answer" : '')
            .'. Review and publish them when you are ready.');
    }

    private function reindex(KnowledgeFaq $faq): void
    {
        $this->index->syncFaqChunks($faq->refresh());

        if ($faq->status->isRetrievable()) {
            EmbedKnowledgeBaseJob::dispatch($faq->knowledge_base_id);
        }
    }

    private function resolveFaq(string $uuid): KnowledgeFaq
    {
        $faq = KnowledgeFaq::query()->where('uuid', $uuid)->first();

        if (! $faq || ! in_array($faq->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        return $faq;
    }

    private function faqSourceFor(int $baseId, ?int $actorId): KnowledgeSource
    {
        return KnowledgeSource::firstOrCreate(
            ['knowledge_base_id' => $baseId, 'source_type' => SourceType::Faq->value, 'knowledge_collection_id' => null],
            [
                'name' => 'FAQs',
                'description' => 'Structured question-and-answer knowledge maintained in PromptBot.',
                'status' => SourceStatus::Ready->value,
                'created_by' => $actorId,
            ]
        );
    }
}
