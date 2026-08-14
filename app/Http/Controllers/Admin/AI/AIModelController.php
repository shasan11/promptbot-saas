<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AI\AiModelStoreRequest;
use App\Http\Requests\Admin\AI\AiModelUpdateRequest;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AIModelController extends Controller
{
    public function index(): Response
    {
        $providers = AiProvider::query()
            ->with(['models' => fn ($query) => $query->orderBy('display_name')])
            ->orderBy('priority')
            ->get()
            ->map(fn (AiProvider $provider) => [
                'id' => $provider->id,
                'name' => $provider->name,
                'driver' => $provider->driver->value,
                'driver_label' => $provider->driver->label(),
                'is_enabled' => $provider->is_enabled,
                'models' => $provider->models->map(fn (AiModel $model) => $this->present($model))->values(),
            ]);

        return Inertia::render('Admin/AI/Models/Index', [
            'providers' => $providers,
        ]);
    }

    public function store(AiModelStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $model = AiModel::create($request->validated());
        $auditLog->record('ai.model.created', $model, ['new_values' => $request->validated()]);

        return back()->with('status', 'Model added.');
    }

    public function update(AiModelUpdateRequest $request, AiModel $model, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $model->only(array_keys($request->validated()));
        $model->update($request->validated());

        $auditLog->record('ai.model.updated', $model, [
            'old_values' => $oldValues,
            'new_values' => $request->validated(),
        ]);

        return back()->with('status', 'Model updated.');
    }

    public function toggle(AiModel $model, AuditLogService $auditLog): RedirectResponse
    {
        $model->update(['is_enabled' => ! $model->is_enabled]);
        $auditLog->record($model->is_enabled ? 'ai.model.enabled' : 'ai.model.disabled', $model);

        return back()->with('status', $model->is_enabled ? 'Model enabled.' : 'Model disabled.');
    }

    public function destroy(AiModel $model, AuditLogService $auditLog): RedirectResponse
    {
        $auditLog->record('ai.model.deleted', $model, ['severity' => 'warning']);
        $model->delete();

        return back()->with('status', 'Model deleted.');
    }

    private function present(AiModel $model): array
    {
        return [
            'id' => $model->id,
            'ai_provider_id' => $model->ai_provider_id,
            'model_key' => $model->model_key,
            'display_name' => $model->display_name,
            'capability' => $model->capability->value,
            'context_window' => $model->context_window,
            'max_output_tokens' => $model->max_output_tokens,
            'embedding_dimensions' => $model->embedding_dimensions,
            'input_cost_per_million_tokens' => (float) $model->input_cost_per_million_tokens,
            'output_cost_per_million_tokens' => (float) $model->output_cost_per_million_tokens,
            'supports_streaming' => $model->supports_streaming,
            'supports_json_mode' => $model->supports_json_mode,
            'is_enabled' => $model->is_enabled,
            'is_default_for_capability' => $model->is_default_for_capability,
        ];
    }
}
