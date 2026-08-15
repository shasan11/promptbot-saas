<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\Run;
use App\Models\AI\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function usage(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.usage.view'), 403);
        $from = now()->subDays(29)->startOfDay();
        return Inertia::render('Tenant/Admin/AI/Usage', [
            'totals' => UsageLog::query()->where('occurred_at', '>=', $from)->selectRaw('COALESCE(SUM(input_tokens),0) input_tokens, COALESCE(SUM(output_tokens),0) output_tokens, COUNT(DISTINCT ai_run_id) runs')->first(),
            'costTotals' => UsageLog::query()->where('occurred_at', '>=', $from)->whereNotNull('estimated_cost')->whereNotNull('currency')->select('currency')->selectRaw('SUM(estimated_cost) cost')->groupBy('currency')->orderBy('currency')->get(),
            'byProvider' => UsageLog::query()->where('occurred_at', '>=', $from)->select('provider','model','currency')->selectRaw('SUM(COALESCE(input_tokens,0)+COALESCE(output_tokens,0)) tokens, SUM(estimated_cost) cost, COUNT(*) requests')->groupBy('provider','model','currency')->orderByDesc('tokens')->get(),
            'daily' => UsageLog::query()->where('occurred_at', '>=', $from)->selectRaw('DATE(occurred_at) day, SUM(COALESCE(input_tokens,0)+COALESCE(output_tokens,0)) tokens')->groupBy(DB::raw('DATE(occurred_at)'))->orderBy('day')->get(),
        ]);
    }

    public function logs(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.logs.view'), 403);
        $query = Run::query()->with(['agent:id,name,public_uuid','providerConfig:id,name,public_uuid'])->latest();
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('feature')) $query->where('feature', $request->string('feature'));
        return Inertia::render('Tenant/Admin/AI/Logs', ['runs' => $query->limit(200)->get()->map(fn (Run $run) => [
            'public_uuid' => $run->public_uuid, 'agent' => $run->agent?->name, 'provider' => $run->providerConfig?->name,
            'feature' => $run->feature, 'operation' => $run->operation, 'status' => $run->status->value,
            'latency_ms' => $run->latency_ms, 'tokens' => $run->total_token_count, 'error_code' => $run->error_code,
            'error_message' => $run->error_message_safe, 'trace_id' => $run->trace_id, 'created_at' => $run->created_at,
        ]), 'filters' => $request->only(['status','feature'])]);
    }
}
