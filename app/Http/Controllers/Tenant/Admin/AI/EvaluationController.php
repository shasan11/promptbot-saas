<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Jobs\AI\RunEvaluationSuiteJob;
use App\Models\AI\Agent;
use App\Models\AI\EvaluationCase;
use App\Models\AI\EvaluationRun;
use App\Models\AI\EvaluationSuite;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EvaluationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.evaluations.view'), 403);
        return Inertia::render('Tenant/Admin/AI/Evaluations/Index', [
            'suites' => EvaluationSuite::query()->with(['agent:id,name,public_uuid','cases','runs' => fn ($query) => $query->latest()->limit(5)])->latest()->get(),
            'agents' => Agent::query()->where('status', 'active')->orderBy('name')->get(['id','public_uuid','name']),
            'canManage' => $request->user('tenant')->can('ai.evaluations.manage'), 'canRun' => $request->user('tenant')->can('ai.evaluations.run'),
        ]);
    }

    public function storeSuite(Request $request, TenantAuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.evaluations.manage'), 403);
        $data = $request->validate(['name' => ['required','string','max:150'], 'description' => ['nullable','string','max:2000'], 'agent_id' => ['required','integer','exists:ai_agents,id']]);
        $suite = EvaluationSuite::query()->create($data + ['active' => true, 'created_by' => $request->user('tenant')->id]);
        $audit->record('ai.evaluation_suite_created', $request->user('tenant'), 'Created AI evaluation suite', $suite, newValues: $data, subjectLabel: $suite->name);
        return back()->with('status', 'Evaluation suite created.');
    }

    public function storeCase(Request $request, EvaluationSuite $suite): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.evaluations.manage'), 403);
        $data = $request->validate(['name' => ['required','string','max:150'], 'category' => ['required','string','max:48'], 'input' => ['required','string','max:100000'], 'assertion_type' => ['required','in:contains,not_contains,regex,citations_required,max_latency_ms'], 'assertion_value' => ['nullable','string','max:1000']]);
        EvaluationCase::query()->create(['suite_id' => $suite->id, 'name' => $data['name'], 'category' => $data['category'], 'input' => $data['input'], 'assertions' => [['type' => $data['assertion_type'], 'value' => $data['assertion_value'] ?? '']], 'active' => true]);
        return back()->with('status', 'Evaluation case added.');
    }

    public function run(Request $request, EvaluationSuite $suite, TenantAuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.evaluations.run'), 403);
        abort_unless($suite->agent?->isDeployed(), 422, 'The evaluation suite needs a deployed agent.');
        $run = EvaluationRun::query()->create(['suite_id' => $suite->id, 'agent_id' => $suite->agent_id, 'agent_version_id' => $suite->agent->deployed_version_id, 'status' => 'queued', 'total_cases' => $suite->cases()->where('active', true)->count(), 'created_by' => $request->user('tenant')->id]);
        RunEvaluationSuiteJob::dispatch($run->id);
        $audit->record('ai.evaluation_queued', $request->user('tenant'), 'Queued AI evaluation suite', $suite, newValues: ['run_uuid' => $run->public_uuid], subjectLabel: $suite->name);
        return back()->with('status', 'Evaluation queued. Results will appear here when complete.');
    }
}
