<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\ManualTextRequest;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\ProcessingStateMachine;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ManualTextController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly ProcessingStateMachine $states,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeDocument::class);

        $query = KnowledgeDocument::query()
            ->summaryColumns()
            ->with(['knowledgeBase:id,uuid,name', 'collection:id,name', 'uploader:id,name'])
            ->where('kind', KnowledgeDocument::KIND_MANUAL_TEXT)
            ->search($request->string('search')->toString())
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/TextSources/Index', [
            'documents' => $query->orderByDesc('updated_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'knowledge_base']),
            'bases' => $this->selectableBases(AccessLevel::Contribute),
            'languages' => config('knowledge.languages'),
            'can' => ['create' => Gate::allows('create', KnowledgeDocument::class)],
        ]);
    }

    public function store(ManualTextRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeDocument::class);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), AccessLevel::Contribute);
        $actor = $request->user('tenant');
        $source = $this->manualTextSourceFor($base->id, $actor?->id);

        $document = KnowledgeDocument::create([
            'knowledge_base_id' => $base->id,
            'knowledge_source_id' => $source->id,
            'knowledge_collection_id' => $request->integer('collection_id') ?: null,
            'title' => $request->string('title')->toString(),
            'kind' => KnowledgeDocument::KIND_MANUAL_TEXT,
            'mime_type' => 'text/markdown',
            'extension' => 'md',
            'file_size' => strlen($request->string('content')->toString()),
            'extracted_text' => $request->string('content')->toString(),
            'language' => $request->string('language')->toString() ?: $base->default_language,
            'effective_from' => $request->date('effective_from'),
            'effective_until' => $request->date('effective_until'),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        if ($tags = $request->input('tags', [])) {
            $document->syncTagNames($tags);
        }

        $job = KnowledgeProcessingJob::create([
            'knowledge_base_id' => $document->knowledge_base_id,
            'knowledge_source_id' => $document->knowledge_source_id,
            'knowledge_document_id' => $document->id,
            'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
            'queue' => config('knowledge.queues.processing'),
            'status' => \App\Enums\Knowledge\ProcessingJobStatus::Queued->value,
            'queued_at' => now(),
            'max_attempts' => (int) config('knowledge.processing.max_attempts'),
            'correlation_id' => (string) Str::uuid(),
            'created_by' => $actor?->id,
        ]);

        $document->refresh();
        $this->states->queue($document);
        ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id);

        $this->auditLog->record(
            'knowledge.manual_text_created',
            $actor,
            "Created manual text source \"{$document->title}\"",
            $document,
        );

        return back()->with('status', 'Manual text saved and queued for indexing.');
    }

    private function manualTextSourceFor(int $baseId, ?int $actorId): KnowledgeSource
    {
        return KnowledgeSource::firstOrCreate(
            ['knowledge_base_id' => $baseId, 'source_type' => SourceType::ManualText->value, 'knowledge_collection_id' => null],
            [
                'name' => 'Manual text',
                'description' => 'Policies, notes and instructions written directly in PromptBot.',
                'status' => SourceStatus::Ready->value,
                'created_by' => $actorId,
            ]
        );
    }
}
