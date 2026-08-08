<?php

namespace App\Http\Controllers\Tenant\Admin\Knowledge;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Admin\Knowledge\Concerns\ResolvesKnowledgeScope;
use App\Models\Knowledge\KnowledgeBase;
use App\Services\Knowledge\KnowledgeAnalyticsService;
use App\Services\Knowledge\KnowledgeLimitService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    use ResolvesKnowledgeScope;

    public function __invoke(KnowledgeAnalyticsService $analytics, KnowledgeLimitService $limits): Response
    {
        Gate::authorize('viewAny', KnowledgeBase::class);

        $allowedIds = $this->allowedBaseIds();

        return Inertia::render('Tenant/Admin/Knowledge/Overview', [
            'stats' => $analytics->overview($allowedIds),
            'activity' => $analytics->recentActivity($allowedIds),
            'gaps' => $analytics->knowledgeGaps($allowedIds, 5),
            'usage' => $limits->usage(),
            'bases' => KnowledgeBase::query()
                ->whereIn('id', $allowedIds ?: [0])
                ->orderByDesc('updated_at')
                ->limit(6)
                ->get(['uuid', 'name', 'description', 'status', 'icon', 'color', 'source_count', 'document_count', 'chunk_count', 'updated_at']),
            'can' => [
                'create' => Gate::allows('create', KnowledgeBase::class),
                'testRetrieval' => $this->actor()->can('knowledge.retrieval.test'),
                'viewAnalytics' => $this->actor()->can('knowledge.analytics.view'),
            ],
        ]);
    }
}
