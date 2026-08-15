<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Agent;
use App\Models\AI\Suggestion;
use App\Models\Inbox\Conversation;
use App\Models\Ticket\Ticket;
use App\Services\AI\TenantAgentRuntime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportInsightController extends Controller
{
    public function __invoke(Request $request, TenantAgentRuntime $runtime): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('reports.view') && $request->user('tenant')->can('ai.copilot.use'), 403);
        $data = $request->validate(['from' => ['required','date'], 'to' => ['required','date','after_or_equal:from']]);
        $agent = Agent::query()->where('status', 'active')->firstOrFail();
        $metrics = [
            'period' => $data, 'conversations' => Conversation::query()->whereBetween('created_at', [$data['from'],$data['to']])->count(),
            'resolved_conversations' => Conversation::query()->whereBetween('created_at', [$data['from'],$data['to']])->whereIn('status', ['resolved','closed'])->count(),
            'tickets' => Ticket::query()->whereBetween('created_at', [$data['from'],$data['to']])->count(),
            'average_first_response_minutes' => (float) (Conversation::query()->whereBetween('created_at', [$data['from'],$data['to']])->whereNotNull('first_response_at')->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, first_message_at, first_response_at)) value')->value('value') ?? 0),
            'sla_breaches' => DB::table('sla_instances')->whereBetween('created_at', [$data['from'],$data['to']])->whereNotNull('breached_at')->count(),
        ];
        $result = $runtime->chat($agent, 'Explain notable patterns, risks, and operational questions from these pre-aggregated metrics. Do not infer causes or facts not present. Clearly label observations and possible follow-up questions. Metrics: '.json_encode($metrics), $request->user('tenant'), 'report_insight', 'summarize_metrics', false);
        Suggestion::query()->where('public_uuid', $result['suggestion_uuid'])->update(['resource_type' => 'reporting', 'type' => 'report_insight', 'structured_payload' => ['filters' => $data]]);
        return back()->with('status', 'AI report observations generated from the aggregated metrics.');
    }
}
