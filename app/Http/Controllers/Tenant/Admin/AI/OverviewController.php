<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Agent;
use App\Models\AI\ApprovalRequest;
use App\Models\AI\ProviderConfig;
use App\Models\AI\Run;
use App\Models\AI\Suggestion;
use App\Models\AI\UsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.view'), 403);

        $runs = Run::query()->where('created_at', '>=', now()->subDays(30));
        return Inertia::render('Tenant/Admin/AI/Overview', [
            'metrics' => [
                'providers' => ProviderConfig::query()->count(),
                'active_agents' => Agent::query()->where('status', 'active')->count(),
                'runs_30d' => (clone $runs)->count(),
                'successful_runs_30d' => (clone $runs)->where('status', 'completed')->count(),
                'pending_approvals' => ApprovalRequest::query()->where('status', 'pending')->count(),
                'pending_suggestions' => Suggestion::query()->where('status', 'pending')->count(),
                'tokens_30d' => (int) UsageLog::query()->where('created_at', '>=', now()->subDays(30))->selectRaw('COALESCE(SUM(COALESCE(input_tokens,0) + COALESCE(output_tokens,0)),0) total')->value('total'),
            ],
            'recentRuns' => Run::query()->with('agent:id,name,public_uuid')->latest()->limit(8)->get()
                ->map(fn (Run $run) => [
                    'public_uuid' => $run->public_uuid, 'agent' => $run->agent?->name,
                    'status' => $run->status->value, 'trigger' => $run->operation,
                    'duration_ms' => $run->latency_ms, 'created_at' => $run->created_at,
                ]),
        ]);
    }
}
