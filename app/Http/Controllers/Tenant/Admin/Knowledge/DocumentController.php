<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\DocumentStatus;
use App\Enums\Knowledge\SourceStatus;
use App\Enums\Knowledge\SourceType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\DocumentUploadRequest;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Jobs\Knowledge\PurgeKnowledgeResourceJob;
use App\Models\Knowledge\KnowledgeDocument;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\DocumentUploadService;
use App\Services\Knowledge\KnowledgeIndexService;
use App\Services\Knowledge\KnowledgeStorage;
use App\Services\Knowledge\ProcessingStateMachine;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __construct(
        private readonly DocumentUploadService $uploads,
        private readonly KnowledgeStorage $storage,
        private readonly KnowledgeIndexService $index,
        private readonly ProcessingStateMachine $states,
        private readonly TenantAuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeDocument::class);

        $query = KnowledgeDocument::query()
            ->summaryColumns()
            ->with(['knowledgeBase:id,uuid,name', 'source:id,uuid,name,source_type', 'collection:id,name', 'uploader:id,name'])
            ->search($request->string('search')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('extension', $request->string('type')))
            ->when($request->filled('language'), fn ($q) => $q->where('language', $request->string('language')))
            ->when($request->boolean('has_errors'), fn ($q) => $q->whereNotNull('last_error'))
            ->when($request->filled('knowledge_base'), function ($q) use ($request): void {
                $q->whereHas('knowledgeBase', fn ($b) => $b->where('uuid', $request->string('knowledge_base')));
            });

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Documents/Index', [
            'documents' => $query->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'type', 'language', 'knowledge_base', 'has_errors']),
            'bases' => $this->selectableBases(),
            'statuses' => array_map(fn (DocumentStatus $s) => ['value' => $s->value, 'label' => $s->label()], DocumentStatus::cases()),
            'languages' => config('knowledge.languages'),
            'can' => ['upload' => Gate::allows('create', KnowledgeDocument::class)],
        ]);
    }

    /**
     * Handles a multi-file upload.
     *
     * Each file succeeds or fails on its own: one rejected file in a batch of
     * twenty must not discard the nineteen that were fine. The response reports
     * per-file outcomes so the UI can show exactly what happened and why.
     */
    public function store(DocumentUploadRequest $request): RedirectResponse
    {
        Gate::authorize('create', KnowledgeDocument::class);

        $base = $this->resolveBase($request->string('knowledge_base')->toString(), \App\Enums\Knowledge\AccessLevel::Contribute);
        $actor = $request->user('tenant');

        $source = $this->uploadSourceFor($base->id, $request->integer('collection_id') ?: null, $actor?->id);

        $results = ['stored' => 0, 'skipped' => 0, 'replaced' => 0, 'failed' => []];

        foreach ($request->file('files', []) as $file) {
            try {
                $outcome = $this->uploads->store(
                    $source,
                    $file,
                    $actor,
                    $request->string('on_duplicate')->toString() ?: DocumentUploadService::ON_DUPLICATE_SKIP,
                    $request->integer('collection_id') ?: null,
                );

                match ($outcome['status']) {
                    'skipped_duplicate' => $results['skipped']++,
                    'replaced' => $results['replaced']++,
                    default => $results['stored']++,
                };

                if ($document = $outcome['document']) {
                    if ($tags = $request->input('tags', [])) {
                        $document->syncTagNames($tags);
                    }

                    $this->dispatchProcessing($document, $actor?->id);

                    $this->auditLog->record(
                        'knowledge.document_uploaded',
                        $actor,
                        "Uploaded \"{$document->original_filename}\" to \"{$base->name}\"",
                        $document,
                        newValues: ['size' => $document->file_size, 'type' => $document->mime_type],
                    );
                }
            } catch (\App\Exceptions\Knowledge\KnowledgeException $e) {
                $results['failed'][] = [
                    'filename' => $file->getClientOriginalName(),
                    'reason' => $e->operatorMessage(),
                ];
            }
        }

        return back()->with('status', $this->uploadSummary($results))->with('uploadResults', $results);
    }

    public function show(string $document): Response
    {
        $record = $this->resolveDocument($document);
        Gate::authorize('view', $record);

        return Inertia::render('Tenant/Admin/Knowledge/Documents/Show', [
            'document' => $record->load(['knowledgeBase:id,uuid,name', 'source:id,uuid,name,source_type', 'collection:id,name', 'uploader:id,name', 'tags:id,name,slug', 'websitePage']),
            // The body is fetched explicitly and truncated: the preview pane
            // does not need a 40 MB string, and the list query never selects it.
            'extractedText' => Str::limit(
                (string) KnowledgeDocument::query()->whereKey($record->id)->value('extracted_text'),
                200_000,
                "\n\n… (preview truncated)"
            ),
            'chunks' => $record->chunks()
                ->limit(200)
                ->get(['uuid', 'chunk_index', 'content', 'token_count', 'character_count', 'language', 'metadata', 'embedding_status', 'is_retrievable', 'embedded_at']),
            'versions' => $record->versions()->with('author:id,name')->limit(50)->get(),
            'logs' => $record->processingLogs()->limit(50)->get(),
            'failures' => $record->failures()->limit(20)->get(),
            'can' => [
                'download' => Gate::allows('download', $record),
                'delete' => Gate::allows('delete', $record),
                'reindex' => Gate::allows('reindex', $record),
                'replace' => Gate::allows('replace', $record),
                'viewTechnicalDetails' => $this->actor()->can('knowledge.manage'),
            ],
        ]);
    }

    /**
     * Streams the original file.
     *
     * The route is signed AND policy-checked. The signature stops a leaked URL
     * from being replayed indefinitely; the policy check is what actually
     * enforces access, because a signed URL says nothing about who is holding it.
     */
    public function download(string $document)
    {
        $record = $this->resolveDocument($document);
        Gate::authorize('download', $record);

        if (! $record->hasStoredFile()) {
            abort(404, 'This knowledge item has no original file — its content was written directly in PromptBot.');
        }

        $this->auditLog->record(
            'knowledge.document_downloaded',
            request()->user('tenant'),
            "Downloaded \"{$record->original_filename}\"",
            $record,
        );

        return $this->storage->disk()->download($record->storage_path, $record->original_filename);
    }

    public function reindex(string $document): RedirectResponse
    {
        $record = $this->resolveDocument($document);
        Gate::authorize('reindex', $record);

        if (! $this->states->requeueForRetry($record)) {
            return back()->with('error', 'This document is already being processed.');
        }

        // force: true so an unchanged content hash does not short-circuit the
        // run — an explicit re-index means "do it again regardless".
        $this->dispatchProcessing($record, $this->actor()->id, force: true);

        return back()->with('status', 'Re-processing this document. Its current answers keep working until the new version is ready.');
    }

    public function destroy(Request $request, string $document): RedirectResponse
    {
        $record = $this->resolveDocument($document);
        Gate::authorize('delete', $record);

        $title = $record->title;
        $purge = $request->boolean('purge');

        // Withdrawn from retrieval first, then soft-deleted: the guarantee is
        // that deleted knowledge stops answering immediately, whatever happens
        // to the rows afterwards.
        $this->index->withdrawDocument($record);
        $record->delete();

        if ($purge) {
            PurgeKnowledgeResourceJob::dispatch('document', $record->id);
        }

        $this->auditLog->record(
            'knowledge.document_deleted',
            $request->user('tenant'),
            "Deleted \"{$title}\"",
            subjectType: KnowledgeDocument::class,
            subjectLabel: $title,
            metadata: ['uuid' => $record->uuid, 'purged' => $purge],
        );

        return redirect()
            ->route('tenant.admin.knowledge.documents.index')
            ->with('status', "\"{$title}\" was removed from your knowledge base.");
    }

    private function resolveDocument(string $uuid): KnowledgeDocument
    {
        $document = KnowledgeDocument::query()->where('uuid', $uuid)->first();

        if (! $document || ! in_array($document->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        return $document;
    }

    private function dispatchProcessing(KnowledgeDocument $document, ?int $actorId, bool $force = false): void
    {
        // The operator-visible job row is created at dispatch, not when a worker
        // starts, so the Processing Queue shows queued work immediately.
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
            'created_by' => $actorId,
        ]);

        $this->states->queue($document);

        ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id, $force);
    }

    /**
     * Uploads land in a per-base "Uploaded files" source, created on demand.
     * Grouping them gives operators one thing to disable, re-index or delete
     * rather than a source per file.
     */
    private function uploadSourceFor(int $baseId, ?int $collectionId, ?int $actorId): KnowledgeSource
    {
        return KnowledgeSource::firstOrCreate(
            [
                'knowledge_base_id' => $baseId,
                'source_type' => SourceType::File->value,
                'knowledge_collection_id' => $collectionId,
            ],
            [
                'name' => 'Uploaded files',
                'description' => 'Documents uploaded directly to this knowledge base.',
                'status' => SourceStatus::Pending->value,
                'created_by' => $actorId,
            ]
        );
    }

    /** @param  array<string, mixed>  $results */
    private function uploadSummary(array $results): string
    {
        $parts = [];

        if ($results['stored']) {
            $parts[] = "{$results['stored']} file(s) uploaded and queued for processing";
        }

        if ($results['replaced']) {
            $parts[] = "{$results['replaced']} replaced";
        }

        if ($results['skipped']) {
            $parts[] = "{$results['skipped']} skipped as duplicates";
        }

        if ($failed = count($results['failed'])) {
            $parts[] = "{$failed} rejected";
        }

        return $parts ? ucfirst(implode(', ', $parts)).'.' : 'No files were processed.';
    }
}
