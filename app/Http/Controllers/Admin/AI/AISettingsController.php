<?php

namespace App\Http\Controllers\Admin\AI;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global AI settings (group=ai on the existing platform_settings table),
 * kept as its own controller/page rather than folded into the generic
 * SettingsController so the "AI & LLM" nav section is self-contained.
 */
class AISettingsController extends Controller
{
    private function fields(): array
    {
        return [
            'enabled' => ['label' => 'Master AI enabled', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => false, 'help' => 'Kill switch for all AI functionality platform-wide.'],
            'fallback_enabled' => ['label' => 'Provider/model fallback', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => true],
            'request_timeout_seconds' => ['label' => 'Default request timeout (seconds)', 'type' => 'number', 'rules' => ['sometimes', 'integer', 'min:1', 'max:600'], 'default' => 30],
            'max_retries' => ['label' => 'Default max retries', 'type' => 'number', 'rules' => ['sometimes', 'integer', 'min:0', 'max:10'], 'default' => 2],
            'circuit_breaker_enabled' => ['label' => 'Circuit breaker', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => true],
            'circuit_breaker_failure_threshold' => ['label' => 'Circuit breaker failure threshold', 'type' => 'number', 'rules' => ['sometimes', 'integer', 'min:1', 'max:100'], 'default' => 5],
            'circuit_breaker_cooldown_seconds' => ['label' => 'Circuit breaker cooldown (seconds)', 'type' => 'number', 'rules' => ['sometimes', 'integer', 'min:1', 'max:3600'], 'default' => 120],
            'log_requests' => ['label' => 'Log AI requests', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => true],
            'store_prompts' => ['label' => 'Store prompt content in logs', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => false, 'help' => 'Off by default for privacy. Currently has no storage column — reserved for a future logs viewer.'],
            'store_responses' => ['label' => 'Store AI responses in logs', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => false],
            'monthly_budget_usd' => ['label' => 'Monthly spending warning (USD, 0 = unlimited)', 'type' => 'number', 'rules' => ['sometimes', 'numeric', 'min:0'], 'default' => 0],
            'usage_retention_days' => ['label' => 'Usage log retention (days)', 'type' => 'number', 'rules' => ['sometimes', 'integer', 'min:1', 'max:3650'], 'default' => 90],
            'allow_tenant_ai' => ['label' => 'Allow tenant AI access', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => false],
            'allow_byok' => ['label' => 'Allow tenant bring-your-own-key', 'type' => 'checkbox', 'rules' => ['sometimes', 'boolean'], 'default' => false],
        ];
    }

    public function edit(PlatformSettingsService $settings): Response
    {
        $current = $settings->group('ai');

        $fields = collect($this->fields())->map(fn (array $field, string $key) => [
            'key' => $key,
            'label' => $field['label'],
            'type' => $field['type'],
            'help' => $field['help'] ?? null,
            'value' => $current[$key] ?? $field['default'],
        ])->values();

        return Inertia::render('Admin/AI/Settings', ['fields' => $fields]);
    }

    public function update(Request $request, PlatformSettingsService $settings, AuditLogService $auditLog): RedirectResponse
    {
        $fields = $this->fields();
        $rules = collect($fields)->mapWithKeys(fn (array $field, string $key) => [$key => $field['rules']])->all();
        $validated = $request->validate($rules);

        $oldValues = [];
        $newValues = [];

        foreach ($validated as $key => $value) {
            $setting = PlatformSetting::query()->where('group', 'ai')->where('key', $key)->first();
            $oldValues[$key] = data_get($setting?->value, 'value');
            $newValues[$key] = $value;

            PlatformSetting::updateOrCreate(
                ['group' => 'ai', 'key' => $key],
                ['id' => $setting?->id ?? (string) Str::uuid(), 'value' => ['value' => $value]]
            );
        }

        $auditLog->record('ai.settings.updated', null, [
            'entity_type' => 'PlatformSetting',
            'entity_id' => 'ai',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $settings->clear()->apply();

        return back()->with('status', 'AI settings updated.');
    }
}
