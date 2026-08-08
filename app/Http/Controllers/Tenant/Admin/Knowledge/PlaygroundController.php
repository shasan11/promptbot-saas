<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Enums\Knowledge\RetrievalMode;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Http\Requests\Tenant\Knowledge\RetrievalTestRequest;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\Knowledge\Data\RetrievalHit;
use App\Services\Knowledge\Data\RetrievalQuery;
use App\Services\Knowledge\KnowledgeRetrievalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Retrieval Playground: run a query exactly as an agent would, and see what
 * comes back before connecting it to anything that talks to customers.
 */
class PlaygroundController extends Controller
{
    use ResolvesKnowledgeScope;

    public function index(): Response
    {
        Gate::authorize('viewAny', KnowledgeBase::class);
        abort_unless($this->actor()->can('knowledge.retrieval.test'), 403);

        $bases = $this->selectableBases();

        return Inertia::render('Tenant/Admin/Knowledge/Playground/Index', [
            'bases' => $bases,
            'collections' => \App\Models\Knowledge\KnowledgeCollection::query()
                ->whereIn('knowledge_base_id', $bases->pluck('id'))
                ->orderBy('name')
                ->get(['id', 'name', 'knowledge_base_id']),
            'modes' => array_map(fn (RetrievalMode $m) => [
                'value' => $m->value, 'label' => $m->label(), 'description' => $m->description(),
            ], RetrievalMode::cases()),
            'defaults' => [
                'mode' => config('knowledge.retrieval.default_mode'),
                'top_k' => config('knowledge.retrieval.default_top_k'),
                'similarity_threshold' => config('knowledge.retrieval.default_similarity_threshold'),
                'rerank' => config('knowledge.retrieval.reranking.enabled_by_default'),
            ],
            'canDebug' => $this->actor()->can('knowledge.manage'),
        ]);
    }

    public function retrieve(RetrievalTestRequest $request, KnowledgeRetrievalService $retrieval): JsonResponse
    {
        abort_unless($this->actor()->can('knowledge.retrieval.test'), 403);

        $actor = $this->actor();

        // Retrieval costs an embedding call per query. Rate-limiting keeps an
        // open playground tab (or an enthusiastic script) from running up a
        // provider bill.
        $limiterKey = 'knowledge-playground:'.(tenancy()->initialized ? tenant('id') : 'central').':'.$actor->id;

        if (RateLimiter::tooManyAttempts($limiterKey, (int) config('knowledge.rate_limits.playground_per_minute'))) {
            return response()->json([
                'message' => 'You are testing retrieval very quickly. Wait '
                    .RateLimiter::availableIn($limiterKey).' seconds and try again.',
            ], 429);
        }

        RateLimiter::hit($limiterKey, 60);

        // The scope is derived from the actor, never from the request. A
        // playground user cannot widen their own reach by posting extra
        // knowledge base UUIDs.
        $allowedBaseIds = $this->allowedBaseIds();

        $requestedUuids = $request->input('knowledge_bases', []);

        if ($requestedUuids) {
            $requestedIds = KnowledgeBase::query()
                ->whereIn('uuid', $requestedUuids)
                ->pluck('id')
                ->all();

            $allowedBaseIds = array_values(array_intersect($allowedBaseIds, $requestedIds));
        }

        $primary = $allowedBaseIds ? KnowledgeBase::find($allowedBaseIds[0]) : null;

        $collectionScope = $this->permissions()->allowedCollectionIds($actor, $allowedBaseIds);

        // A collection filter chosen in the UI narrows the permitted set; it
        // can never widen it.
        if ($requested = $request->input('collection_ids', [])) {
            $collectionScope = $collectionScope === null
                ? array_map('intval', $requested)
                : array_values(array_intersect($collectionScope, array_map('intval', $requested)));
        }

        $canDebug = $actor->can('knowledge.manage');

        $query = new RetrievalQuery(
            query: $request->string('query')->toString(),
            knowledgeBaseIds: $allowedBaseIds,
            allowedCollectionIds: $collectionScope,
            sourceIds: array_map('intval', $request->input('source_ids', [])),
            tags: $request->input('tags', []),
            language: $request->string('language')->toString() ?: null,
            mode: RetrievalMode::tryFrom($request->string('mode')->toString()) ?? ($primary?->retrieval_mode ?? RetrievalMode::Hybrid),
            topK: $request->integer('top_k') ?: ($primary?->top_k ?? 5),
            candidatePool: $primary?->candidate_pool ?? 20,
            similarityThreshold: $request->has('similarity_threshold')
                ? (float) $request->input('similarity_threshold')
                : (float) ($primary?->similarity_threshold ?? 0.7),
            rerank: $request->boolean('rerank', (bool) ($primary?->reranking_enabled ?? true)),
            preferRecent: (bool) ($primary?->prefer_recent_content ?? false),
            excludeExpired: (bool) ($primary?->exclude_expired_content ?? true),
            maxContextTokens: $primary?->max_context_tokens ?? 8000,
            channel: \App\Models\Knowledge\KnowledgeRetrievalLog::CHANNEL_PLAYGROUND,
            debug: $canDebug && $request->boolean('debug'),
        );

        $outcome = $retrieval->retrieve($query, $primary);

        $payload = [
            'query' => $query->query,
            'results' => array_map(fn (RetrievalHit $hit) => $hit->toArray(), $outcome->hits),
            'citations' => $outcome->citations(),
            'timings' => $outcome->timings,
            'context_tokens' => $outcome->contextTokens,
            'zero_results' => $outcome->isEmpty(),
            'log_uuid' => $outcome->logUuid,
        ];

        if ($request->boolean('generate_answer')) {
            $payload['answer_preview'] = $this->answerPreview($outcome->hits);
        }

        // Debug output exposes discarded candidates, threshold decisions and the
        // assembled context. That is genuinely useful to whoever tunes the base
        // and needlessly revealing to everyone else, so it is gated on
        // knowledge.manage rather than on the request flag alone.
        if ($query->debug) {
            $payload['debug'] = [
                'semantic_candidates' => $outcome->semanticCandidates,
                'keyword_candidates' => $outcome->keywordCandidates,
                'similarity_threshold' => $query->similarityThreshold,
                'mode' => $query->mode->value,
                'reranked' => $query->rerank,
                'scoped_knowledge_bases' => count($allowedBaseIds),
                'discarded' => array_map(fn (RetrievalHit $hit) => $hit->toArray(), array_slice($outcome->discarded, 0, 20)),
                'context' => $outcome->context,
            ];
        }

        return response()->json($payload);
    }

    /** @param  array<int, RetrievalHit>  $hits */
    private function answerPreview(array $hits): array
    {
        if ($hits === []) {
            return [
                'answer' => 'I could not find enough matching knowledge to answer this safely.',
                'confidence' => 'low',
                'sources_used' => [],
            ];
        }

        $sentences = [];
        $sources = [];

        foreach (array_slice($hits, 0, 3) as $index => $hit) {
            $citation = $hit->chunk->citation();
            $sourceLabel = $citation['document_title'] ?? $citation['url'] ?? $citation['faq_question'] ?? 'Knowledge source';
            $sources[] = array_merge($citation, ['rank' => $index + 1, 'score' => round($hit->finalScore, 5)]);

            $text = trim(preg_replace('/\s+/', ' ', $hit->chunk->content) ?? '');
            $firstSentence = preg_split('/(?<=[.!?])\s+/', $text, 2)[0] ?? $text;

            if ($firstSentence !== '') {
                $sentences[] = rtrim($firstSentence, '.').' ['.($index + 1).']';
            }
        }

        $topScore = $hits[0]->finalScore;

        return [
            'answer' => $sentences
                ? implode(' ', $sentences)
                : 'The retrieved sources matched, but there was no concise text to preview.',
            'confidence' => $topScore >= 0.75 ? 'high' : ($topScore >= 0.45 ? 'medium' : 'low'),
            'sources_used' => $sources,
            'note' => 'Preview is grounded only in retrieved excerpts and does not call a chat model.',
        ];
    }
}
