<?php

namespace App\Http\Controllers\Tenant\Admin\AI;

use App\Http\Controllers\Controller;
use App\Services\AI\AISettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\SaaS\TenantFeatureService;

class SettingsController extends Controller
{
    public function edit(Request $request, AISettingsService $settings): Response
    {
        abort_unless($request->user('tenant')->can('ai.settings.manage'), 403);
        return Inertia::render('Tenant/Admin/AI/Settings', [
            'settings' => $settings->current(),
            'platform' => [
                'enabled' => (bool) config('ai.enabled'),
                'max_retention_days' => (int) config('ai.retention.maximum_days'),
                'private_provider_endpoints_enabled' => (bool) config('ai.providers.ollama.allow_private_endpoints'),
                'autonomous_replies_enabled' => (bool) config('ai.safety.autonomous_replies_enabled'),
                'autonomous_plan_enabled' => app(TenantFeatureService::class)->enabled('ai_autonomous_replies'),
            ],
        ]);
    }

    public function update(Request $request, AISettingsService $settings): RedirectResponse
    {
        abort_unless($request->user('tenant')->can('ai.settings.manage'), 403);
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'human_review_required' => ['required', 'boolean'],
            'require_grounding' => ['required', 'boolean'],
            'require_citations' => ['required', 'boolean'],
            'allow_private_provider_endpoints' => ['required', 'boolean'],
            'background_inbox_analysis' => ['required', 'boolean'],
            'autonomous_replies_enabled' => ['required', 'boolean'],
            'log_retention_days' => ['required', 'integer', 'min:1'],
            'monthly_token_budget' => ['nullable', 'integer', 'min:1'],
            'monthly_cost_budget' => ['nullable', 'numeric', 'min:0.01'],
        ]);
        if ($validated['allow_private_provider_endpoints'] && ! config('ai.providers.ollama.allow_private_endpoints')) {
            $validated['allow_private_provider_endpoints'] = false;
        }
        if ($validated['autonomous_replies_enabled'] && (! config('ai.safety.autonomous_replies_enabled') || ! app(TenantFeatureService::class)->enabled('ai_autonomous_replies'))) {
            $validated['autonomous_replies_enabled'] = false;
        }
        $settings->update($validated, $request->user('tenant'));
        return back()->with('status', 'AI workspace settings saved.');
    }
}
