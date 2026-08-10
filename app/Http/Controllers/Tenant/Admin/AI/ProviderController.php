<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AI\SaveProviderRequest;
use App\Models\AI\ProviderConfig;
use App\Services\AI\ProviderConfigService;
use App\Services\AI\ProviderHealthService;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')->can('ai.providers.view'), 403);
        return Inertia::render('Tenant/Admin/AI/Providers/Index', [
            'providers' => ProviderConfig::query()->latest()->get()->map->safePayload(),
            'catalogue' => collect(config('ai.providers'))->map(fn (array $definition, string $key) => [
                'key' => $key, 'label' => $definition['label'],
                'requires_api_key' => $definition['requires_api_key'],
                'capabilities' => $definition['capabilities'],
            ])->values(),
            'canManage' => $request->user('tenant')->can('ai.providers.manage'),
        ]);
    }

    public function store(SaveProviderRequest $request, ProviderConfigService $service): RedirectResponse
    {
        $service->create($request->validated(), $request->user('tenant'));
        return back()->with('status', 'AI provider configuration created.');
    }

    public function update(SaveProviderRequest $request, ProviderConfig $provider, ProviderConfigService $service): RedirectResponse
    {
        $service->update($provider, $request->validated(), $request->user('tenant'));
        return back()->with('status', 'AI provider configuration updated.');
    }

    public function test(Request $request, ProviderConfig $provider, ProviderHealthService $health, TenantAuditLogService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.providers.test'), 403);
        $result = $health->test($provider);
        $audit->record('ai.provider_tested', $request->user('tenant'), $result['message'], $provider,
            newValues: ['ok' => $result['ok'], 'latency_ms' => $result['latency_ms']], subjectLabel: $provider->name);
        return back()->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function destroy(Request $request, ProviderConfig $provider, ProviderConfigService $service): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.providers.manage'), 403);
        $service->delete($provider, $request->user('tenant'));
        return back()->with('status', 'AI provider configuration deleted.');
    }
}
