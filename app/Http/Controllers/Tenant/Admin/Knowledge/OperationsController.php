<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\AccessLevel;
use App\Enums\Knowledge\FailureCategory;
use App\Enums\Knowledge\ProcessingJobStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Jobs\Knowledge\ProcessKnowledgeDocumentJob;
use App\Jobs\Knowledge\SyncKnowledgeSourceJob;
use App\Models\Knowledge\KnowledgeBase;
use App\Models\Knowledge\KnowledgeFailure;
use App\Models\Knowledge\KnowledgeProcessingJob;
use App\Models\Knowledge\KnowledgeSource;
use App\Services\Knowledge\KnowledgeAnalyticsService;
use App\Services\Knowledge\ProcessingStateMachine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The operational screens: what is running, what broke, and how retrieval is
 * performing.
 */
class OperationsController extends Controller
{
    use ResolvesKnowledgeScope;

    public function processing(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeSource::class);

        $query = KnowledgeProcessingJob::query()
            ->with(['knowledgeBase:id,uuid,name', 'source:id,uuid,name', 'document:id,uuid,title', 'creator:id,name'])
            ->when(
                $request->string('status')->toString() !== 'all',
                fn ($q) => $q->active(),
                fn ($q) => $q
            );

        $this->scopeToAllowedBases($query);

        return Inertia::render('Tenant/Admin/Knowledge/Processing/Index', [
            'jobs' => $query->orderByDesc('queued_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['status']),
            'summary' => $this->processingSummary(),
            'can' => ['manage' => $this->actor()->can('knowledge.reindex')],
        ]);
    }

    public function cancelJob(string $job): RedirectResponse
    {
        $record = $this->resolveJob($job);
        abort_unless($this->actor()->can('knowledge.reindex'), 403);

        if (! $record->status->isActive()) {
            return back()->with('error', 'That job has already finished.');
        }

        // Cooperative: long-running workers check this flag between units of
        // work, so a crawl stops at the next page rather than being killed
        // mid-write.
        $record->forceFill([
            'cancel_requested' => true,
            'status' => $record->status === ProcessingJobStatus::Queued
                ? ProcessingJobStatus::Cancelled->value
                : $record->status->value,
        ])->save();

        return back()->with('status', $record->status === ProcessingJobStatus::Cancelled
            ? 'Job cancelled.'
            : 'Cancellation requested — the job stops after its current step.');
    }

    public function failures(Request $request): Response
    {
        Gate::authorize('viewAny', KnowledgeSource::class);

        $query = KnowledgeFailure::query()
            ->with(['knowledgeBase:id,uuid,name', 'source:id,uuid,name,source_type', 'document:id,uuid,title'])
            ->unresolved()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')));

        $this->scopeToAllowedBases($query);

        $categories = array_map(fn (FailureCategory $c) => [
            'value' => $c->value,
            'label' => $c->label(),
            'remediation' => $c->remediation(),
            'retryable' => $c->isTransient(),
        ], FailureCategory::cases());

        return Inertia::render('Tenant/Admin/Knowledge/Failures/Index', [
            'failures' => $query->orderByDesc('created_at')->paginate(25)->withQueryString(),
            'filters' => $request->only(['category']),
            'categories' => $categories,
            'can' => [
                'retry' => $this->actor()->can('knowledge.reindex'),
                // Stack traces and file paths stay behind the administrative
                // capability; a support agent gets the actionable message only.
                'viewTechnicalDetails' => $this->actor()->can('knowledge.manage'),
            ],
        ]);
    }

    public function failureDetails(string $failure): \Illuminate\Http\JsonResponse
    {
        $record = $this->resolveFailure($failure);
        abort_unless($this->actor()->can('knowledge.manage'), 403);

        return response()->json([
            'uuid' => $record->uuid,
            'stage' => $record->stage->value,
            'category' => $record->category->value,
            'message' => $record->message,
            'technical_details' => $record->technical_details,
            'attempt' => $record->attempt,
            'created_at' => $record->created_at,
        ]);
    }

    public function retryFailure(string $failure, ProcessingStateMachine $states): RedirectResponse
    {
        $record = $this->resolveFailure($failure);
        abort_unless($this->actor()->can('knowledge.reindex'), 403);

        if ($document = $record->document) {
            if (! $states->requeueForRetry($document)) {
                return back()->with('error', 'That document is already being processed.');
            }

            $job = KnowledgeProcessingJob::create([
                'knowledge_base_id' => $document->knowledge_base_id,
                'knowledge_source_id' => $document->knowledge_source_id,
                'knowledge_document_id' => $document->id,
                'job_type' => KnowledgeProcessingJob::TYPE_DOCUMENT,
                'queue' => config('knowledge.queues.processing'),
                'status' => ProcessingJobStatus::Queued->value,
                'queued_at' => now(),
                'max_attempts' => (int) config('knowledge.processing.max_attempts'),
                'correlation_id' => (string) Str::uuid(),
                'created_by' => $this->actor()->id,
            ]);

            ProcessKnowledgeDocumentJob::dispatch($document->id, $job->id, true);
        } elseif ($source = $record->source) {
            SyncKnowledgeSourceJob::dispatch($source->id, \App\Models\Knowledge\KnowledgeSyncRun::TRIGGER_MANUAL, $this->actor()->id);
        } else {
            return back()->with('error', 'There is nothing left to retry — the item this failure refers to was deleted.');
        }

        $record->forceFill(['resolved_at' => now(), 'resolved_by' => $this->actor()->id])->save();

        return back()->with('status', 'Retrying. Watch the Processing queue for progress.');
    }

    public function dismissFailure(string $failure): RedirectResponse
    {
        $record = $this->resolveFailure($failure);
        abort_unless($this->actor()->can('knowledge.reindex'), 403);

        $record->forceFill(['resolved_at' => now(), 'resolved_by' => $this->actor()->id])->save();

        return back()->with('status', 'Failure dismissed.');
    }

    public function analytics(Request $request, KnowledgeAnalyticsService $analytics): Response
    {
        Gate::authorize('viewAny', KnowledgeBase::class);
        abort_unless($this->actor()->can('knowledge.analytics.view'), 403);

        $days = min(365, max(1, $request->integer('days', 30)));

        return Inertia::render('Tenant/Admin/Knowledge/Analytics/Index', [
            'analytics' => $analytics->analytics($this->allowedBaseIds(), $days),
            'days' => $days,
            'bases' => $this->selectableBases(),
        ]);
    }

    /** Turns a knowledge gap into a draft FAQ, pre-filled with the question asked. */
    public function resolveGap(Request $request, string $gap): RedirectResponse
    {
        abort_unless($this->actor()->can('knowledge.analytics.view'), 403);

        $record = \App\Models\Knowledge\KnowledgeGap::query()->where('uuid', $gap)->firstOrFail();

        if ($record->knowledge_base_id && ! in_array($record->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        $action = $request->string('action')->toString();

        if ($action === 'ignore') {
            $record->forceFill(['status' => \App\Models\Knowledge\KnowledgeGap::STATUS_IGNORED])->save();

            return back()->with('status', 'Gap dismissed.');
        }

        if ($action === 'assign') {
            $request->validate(['assigned_to' => ['required', 'integer', 'exists:users,id']]);

            $record->forceFill([
                'status' => \App\Models\Knowledge\KnowledgeGap::STATUS_ASSIGNED,
                'assigned_to' => $request->integer('assigned_to'),
            ])->save();

            return back()->with('status', 'Gap assigned.');
        }

        return back()->with('error', 'Unknown action.');
    }

    /** @return array<string, int> */
    private function processingSummary(): array
    {
        $ids = $this->allowedBaseIds();

        if (! $ids) {
            return ['queued' => 0, 'running' => 0, 'retrying' => 0, 'failed_today' => 0];
        }

        $counts = KnowledgeProcessingJob::query()
            ->whereIn('knowledge_base_id', $ids)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'queued' => (int) ($counts[ProcessingJobStatus::Queued->value] ?? 0),
            'running' => (int) ($counts[ProcessingJobStatus::Running->value] ?? 0),
            'retrying' => (int) ($counts[ProcessingJobStatus::Retrying->value] ?? 0),
            'failed_today' => KnowledgeProcessingJob::query()
                ->whereIn('knowledge_base_id', $ids)
                ->where('status', ProcessingJobStatus::Failed->value)
                ->where('finished_at', '>=', now()->startOfDay())
                ->count(),
        ];
    }

    private function resolveJob(string $uuid): KnowledgeProcessingJob
    {
        $job = KnowledgeProcessingJob::query()->where('uuid', $uuid)->first();

        if (! $job || ! in_array($job->knowledge_base_id, $this->allowedBaseIds(AccessLevel::Contribute), true)) {
            throw new NotFoundHttpException;
        }

        return $job;
    }

    private function resolveFailure(string $uuid): KnowledgeFailure
    {
        $failure = KnowledgeFailure::query()->with(['document', 'source'])->where('uuid', $uuid)->first();

        if (! $failure || ! in_array($failure->knowledge_base_id, $this->allowedBaseIds(), true)) {
            throw new NotFoundHttpException;
        }

        return $failure;
    }
}
