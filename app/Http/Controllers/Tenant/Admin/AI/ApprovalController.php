<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\AI\ApprovalRequest;
use App\Services\AI\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.approvals.view'), 403);
        $status = $request->string('status', 'pending')->toString();
        return Inertia::render('Tenant/Admin/AI/Approvals/Index', [
            'approvals' => ApprovalRequest::query()->with(['agent:id,name,public_uuid','action:id,name,key'])
                ->when($status !== 'all', fn ($query) => $query->where('status', $status))->latest('requested_at')->limit(100)->get()->map(fn (ApprovalRequest $approval) => [
                    'public_uuid' => $approval->public_uuid, 'agent' => $approval->agent?->name, 'action' => $approval->requested_action,
                    'tool_key' => $approval->action?->key, 'risk_level' => $approval->risk_level->value, 'arguments' => $approval->arguments_redacted,
                    'context' => $approval->context, 'status' => $approval->status->value, 'requested_at' => $approval->requested_at,
                    'expires_at' => $approval->expires_at, 'decision_reason' => $approval->decision_reason,
                ]), 'statusFilter' => $status, 'canDecide' => $request->user('tenant')->can('ai.approvals.decide'),
        ]);
    }

    public function approve(Request $request, ApprovalRequest $approval, ApprovalService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.approvals.decide'), 403);
        $data = $request->validate(['reason' => ['nullable','string','max:2000']]);
        $service->approve($approval, $request->user('tenant'), $data['reason'] ?? null);
        return back()->with('status', 'Action approved and executed.');
    }

    public function reject(Request $request, ApprovalRequest $approval, ApprovalService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.approvals.decide'), 403);
        $data = $request->validate(['reason' => ['nullable','string','max:2000']]);
        $service->reject($approval, $request->user('tenant'), $data['reason'] ?? null);
        return back()->with('status', 'Action rejected.');
    }
}
