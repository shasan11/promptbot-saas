<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AiUsageLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AILogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'provider_driver', 'purpose', 'tenant_id', 'date_from', 'date_to']);

        $logs = AiUsageLog::query()
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['provider_driver'] ?? null, fn ($q, $value) => $q->where('provider_driver', $value))
            ->when($filters['purpose'] ?? null, fn ($q, $value) => $q->where('purpose', $value))
            ->when($filters['tenant_id'] ?? null, fn ($q, $value) => $q->where('tenant_id', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->where('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->where('created_at', '<=', $value.' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (AiUsageLog $log) => [
                'id' => $log->id,
                'created_at' => $log->created_at?->toIso8601String(),
                'tenant_id' => $log->tenant_id,
                'provider_name' => $log->provider_name,
                'provider_driver' => $log->provider_driver,
                'model_key' => $log->model_key,
                'purpose' => $log->purpose,
                'capability' => $log->capability,
                'status' => $log->status,
                'error_code' => $log->error_code,
                'prompt_tokens' => $log->prompt_tokens,
                'completion_tokens' => $log->completion_tokens,
                'total_tokens' => $log->total_tokens,
                'estimated_cost' => (float) $log->estimated_cost,
                'latency_ms' => $log->latency_ms,
                'request_uuid' => $log->request_uuid,
            ]);

        return Inertia::render('Admin/AI/Logs', [
            'logs' => $logs,
            'filters' => $filters,
            'statusOptions' => ['success', 'failed'],
            'purposeOptions' => AiUsageLog::query()->select('purpose')->distinct()->orderBy('purpose')->pluck('purpose'),
            'providerOptions' => AiUsageLog::query()->select('provider_driver')->whereNotNull('provider_driver')->distinct()->orderBy('provider_driver')->pluck('provider_driver'),
        ]);
    }
}
