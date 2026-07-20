<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeatureStoreRequest;
use App\Http\Requests\Admin\FeatureUpdateRequest;
use App\Models\Feature;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Features/Index', [
            'features' => Feature::query()->orderBy('code')->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Features/Create');
    }

    public function store(FeatureStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $feature = Feature::create($request->validated());
        $auditLog->record('feature.created', $feature, ['new_values' => $request->validated()]);

        return redirect()->route('superadmin.features.index')->with('status', 'Feature created.');
    }

    public function show(Feature $feature): Response
    {
        return Inertia::render('Admin/Features/Show', ['feature' => $feature]);
    }

    public function edit(Feature $feature): Response
    {
        return Inertia::render('Admin/Features/Create', ['feature' => $feature]);
    }

    public function update(FeatureUpdateRequest $request, Feature $feature, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $feature->only(array_keys($request->validated()));
        $feature->update($request->validated());
        $auditLog->record('feature.updated', $feature, [
            'old_values' => $oldValues,
            'new_values' => $request->validated(),
        ]);

        return redirect()->route('superadmin.features.index')->with('status', 'Feature updated.');
    }

    public function destroy(Feature $feature, AuditLogService $auditLog): RedirectResponse
    {
        $feature->delete();
        $auditLog->record('feature.deleted', $feature, ['severity' => 'warning']);

        return back()->with('status', 'Feature deleted.');
    }
}
