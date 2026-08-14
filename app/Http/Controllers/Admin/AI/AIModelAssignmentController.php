<?php

namespace App\Http\Controllers\Admin\AI;

use App\Enums\AI\AIPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AI\AiModelAssignmentStoreRequest;
use App\Models\AiModel;
use App\Models\AiModelAssignment;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AIModelAssignmentController extends Controller
{
    public function index(): Response
    {
        $purposes = collect(AIPurpose::cases())->map(function (AIPurpose $purpose) {
            $assignments = AiModelAssignment::query()
                ->where('purpose', $purpose->value)
                ->with('model.provider')
                ->orderBy('priority')
                ->get()
                ->map(fn (AiModelAssignment $assignment) => [
                    'id' => $assignment->id,
                    'priority' => $assignment->priority,
                    'is_enabled' => $assignment->is_enabled,
                    'model' => [
                        'id' => $assignment->model->id,
                        'display_name' => $assignment->model->display_name,
                        'model_key' => $assignment->model->model_key,
                        'provider_name' => $assignment->model->provider->name,
                    ],
                ]);

            return [
                'purpose' => $purpose->value,
                'label' => $purpose->label(),
                'capability' => $purpose->capability()->value,
                'assignments' => $assignments,
            ];
        });

        $availableModels = AiModel::query()
            ->with('provider')
            ->where('is_enabled', true)
            ->whereHas('provider', fn ($query) => $query->where('is_enabled', true))
            ->get()
            ->map(fn (AiModel $model) => [
                'id' => $model->id,
                'display_name' => $model->display_name,
                'model_key' => $model->model_key,
                'capability' => $model->capability->value,
                'provider_name' => $model->provider->name,
            ]);

        return Inertia::render('Admin/AI/Assignments/Index', [
            'purposes' => $purposes,
            'availableModels' => $availableModels,
        ]);
    }

    public function store(AiModelAssignmentStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validated();
        $validated['priority'] ??= (AiModelAssignment::where('purpose', $validated['purpose'])->max('priority') ?? 0) + 10;

        $assignment = AiModelAssignment::create($validated);
        $auditLog->record('ai.settings.updated', null, [
            'entity_type' => 'AiModelAssignment',
            'entity_id' => $assignment->id,
            'new_values' => $validated,
        ]);

        return back()->with('status', 'Model added to purpose.');
    }

    public function update(Request $request, AiModelAssignment $assignment, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate(['priority' => ['sometimes', 'integer', 'min:0'], 'is_enabled' => ['sometimes', 'boolean']]);
        $assignment->update($validated);

        $auditLog->record('ai.settings.updated', null, [
            'entity_type' => 'AiModelAssignment',
            'entity_id' => $assignment->id,
            'new_values' => $validated,
        ]);

        return back()->with('status', 'Assignment updated.');
    }

    public function destroy(AiModelAssignment $assignment, AuditLogService $auditLog): RedirectResponse
    {
        $auditLog->record('ai.settings.updated', null, [
            'entity_type' => 'AiModelAssignment',
            'entity_id' => $assignment->id,
            'new_values' => ['removed' => true],
        ]);
        $assignment->delete();

        return back()->with('status', 'Model removed from purpose.');
    }
}
