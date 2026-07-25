<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanStoreRequest;
use App\Http\Requests\Admin\PlanUpdateRequest;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::query()->with('features')->orderBy('sort_order')->paginate(20),
            'features' => Feature::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Create', ['features' => Feature::query()->orderBy('name')->get()]);
    }

    public function store(PlanStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $plan = Plan::create($request->safe()->except('features'));
        $this->syncFeatures($plan, $request->validated('features', []));
        $auditLog->record('plan.created', $plan, ['new_values' => $request->safe()->except('features')]);

        return redirect()->route('superadmin.plans.index')->with('status', 'Plan created.');
    }

    public function show(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Show', ['plan' => $plan->load('features')]);
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Create', [
            'plan' => $plan->load('features'),
            'features' => Feature::query()->orderBy('name')->get(),
        ]);
    }

    public function update(PlanUpdateRequest $request, Plan $plan, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $plan->only(array_keys($request->safe()->except('features')));
        $plan->update($request->safe()->except('features'));
        $this->syncFeatures($plan, $request->validated('features', []));
        $auditLog->record('plan.updated', $plan, [
            'old_values' => $oldValues,
            'new_values' => $request->safe()->except('features'),
        ]);

        return redirect()->route('superadmin.plans.index')->with('status', 'Plan updated.');
    }

    public function destroy(Plan $plan, AuditLogService $auditLog): RedirectResponse
    {
        $plan->delete();
        $auditLog->record('plan.archived', $plan, ['severity' => 'warning']);

        return back()->with('status', 'Plan archived.');
    }

    private function syncFeatures(Plan $plan, array $features): void
    {
        $plan->features()->sync(collect($features)->mapWithKeys(fn (array $feature) => [
            $feature['id'] => [
                'id' => (string) Str::uuid(),
                'enabled' => (bool) ($feature['enabled'] ?? true),
                'limit' => $feature['limit'] ?? null,
                'unlimited' => (bool) ($feature['unlimited'] ?? false),
            ],
        ])->all());
    }
}
