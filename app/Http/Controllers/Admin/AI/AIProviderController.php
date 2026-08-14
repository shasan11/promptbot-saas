<?php

namespace App\Http\Controllers\Admin\AI;

use App\Enums\AI\AIProviderDriver;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AI\AiProviderStoreRequest;
use App\Http\Requests\Admin\AI\AiProviderUpdateRequest;
use App\Models\AiProvider;
use App\Services\AI\AIProviderRegistry;
use App\Services\AI\Data\TestConnectionResult;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AIProviderController extends Controller
{
    public function index(): Response
    {
        $providers = AiProvider::query()
            ->withCount('models')
            ->orderBy('priority')
            ->get()
            ->map(fn (AiProvider $provider) => $this->present($provider));

        return Inertia::render('Admin/AI/Providers/Index', [
            'providers' => $providers,
            'driverOptions' => $this->driverOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AI/Providers/Form', [
            'driverOptions' => $this->driverOptions(),
        ]);
    }

    public function store(AiProviderStoreRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validated();
        $provider = AiProvider::create($validated);

        $auditLog->record('ai.provider.created', $provider, [
            'new_values' => [
                ...collect($validated)->except(['api_key', 'organization_id', 'extra_headers'])->all(),
                'api_key' => $provider->hasKey() ? '[configured]' : null,
            ],
        ]);

        return redirect()->route('superadmin.ai.providers.index')->with('status', 'Provider created.');
    }

    public function edit(AiProvider $provider): Response
    {
        return Inertia::render('Admin/AI/Providers/Form', [
            'provider' => $this->present($provider),
            'driverOptions' => $this->driverOptions(),
        ]);
    }

    public function update(AiProviderUpdateRequest $request, AiProvider $provider, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validated();
        $keyWasProvided = array_key_exists('api_key', $validated) && filled($validated['api_key']);
        $hadKeyBefore = $provider->hasKey();

        if (array_key_exists('api_key', $validated) && blank($validated['api_key'])) {
            unset($validated['api_key']);
        }

        if (array_key_exists('organization_id', $validated) && blank($validated['organization_id'])) {
            unset($validated['organization_id']);
        }

        $oldValues = collect($validated)->keys()
            ->reject(fn ($key) => in_array($key, ['api_key', 'organization_id', 'extra_headers'], true))
            ->mapWithKeys(fn ($key) => [$key => $provider->getAttribute($key)])
            ->all();

        $provider->fill($validated)->save();

        $auditLog->record('ai.provider.updated', $provider, [
            'old_values' => $oldValues,
            'new_values' => collect($validated)->except(['api_key', 'organization_id', 'extra_headers'])->all(),
        ]);

        if ($keyWasProvided) {
            $auditLog->record('ai.provider.key_updated', $provider, [
                'old_values' => ['api_key' => $hadKeyBefore ? '[configured]' : null],
                'new_values' => ['api_key' => '[configured]'],
            ]);
        }

        return redirect()->route('superadmin.ai.providers.index')->with('status', 'Provider updated.');
    }

    public function removeKey(AiProvider $provider, AuditLogService $auditLog): RedirectResponse
    {
        $provider->update(['api_key' => null, 'organization_id' => null]);

        $auditLog->record('ai.provider.key_removed', $provider, [
            'new_values' => ['api_key' => '[removed]'],
        ]);

        return back()->with('status', 'API key removed.');
    }

    public function toggle(AiProvider $provider, AuditLogService $auditLog): RedirectResponse
    {
        $provider->update(['is_enabled' => ! $provider->is_enabled]);

        $auditLog->record($provider->is_enabled ? 'ai.provider.enabled' : 'ai.provider.disabled', $provider);

        return back()->with('status', $provider->is_enabled ? 'Provider enabled.' : 'Provider disabled.');
    }

    public function test(AiProvider $provider, AIProviderRegistry $registry, AuditLogService $auditLog): RedirectResponse
    {
        try {
            $result = $registry->driverFor($provider)->testConnection();
        } catch (Throwable $exception) {
            report($exception);
            $result = TestConnectionResult::failure('The connection test failed unexpectedly. Check application logs.');
        }

        $provider->update([
            'last_tested_at' => now(),
            'last_test_status' => $result->success ? 'success' : 'failed',
            'last_test_message' => $result->message,
            'last_success_at' => $result->success ? now() : $provider->last_success_at,
        ]);

        $auditLog->record('ai.provider.tested', $provider, [
            'new_values' => ['status' => $result->success ? 'success' : 'failed'],
            'severity' => $result->success ? 'info' : 'warning',
        ]);

        return back()->with($result->success ? 'status' : 'error', $result->message);
    }

    public function destroy(AiProvider $provider, AuditLogService $auditLog): RedirectResponse
    {
        $auditLog->record('ai.provider.deleted', $provider, ['severity' => 'warning']);
        $provider->delete();

        return redirect()->route('superadmin.ai.providers.index')->with('status', 'Provider deleted.');
    }

    private function present(AiProvider $provider): array
    {
        return [
            'id' => $provider->id,
            'driver' => $provider->driver->value,
            'driver_label' => $provider->driver->label(),
            'name' => $provider->name,
            'slug' => $provider->slug,
            'base_url' => $provider->base_url,
            'masked_key' => $provider->maskedKey(),
            'has_key' => $provider->hasKey(),
            'has_organization' => filled($provider->organization_id),
            'is_enabled' => $provider->is_enabled,
            'priority' => $provider->priority,
            'timeout_seconds' => $provider->timeout_seconds,
            'max_retries' => $provider->max_retries,
            'last_tested_at' => $provider->last_tested_at?->toIso8601String(),
            'last_test_status' => $provider->last_test_status,
            'last_test_message' => $provider->last_test_message,
            'last_success_at' => $provider->last_success_at?->toIso8601String(),
            'models_count' => $provider->models_count ?? $provider->models()->count(),
        ];
    }

    private function driverOptions(): array
    {
        return collect(AIProviderDriver::cases())->map(fn (AIProviderDriver $driver) => [
            'value' => $driver->value,
            'label' => $driver->label(),
            'requires_api_key' => $driver->requiresApiKey(),
            'default_base_url' => $driver->defaultBaseUrl(),
            'is_openai_compatible' => $driver->isOpenAiCompatible(),
        ])->values()->all();
    }
}
