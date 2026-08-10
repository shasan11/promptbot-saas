<?php

namespace App\Services\AI;

use App\Models\User;
use App\Services\Tenant\TenantAuditLogService;
use App\Services\Tenant\TenantSettingsService;

class AISettingsService
{
    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly TenantAuditLogService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function current(): array
    {
        return [
            'enabled' => (bool) $this->settings->get('ai.enabled', true),
            'human_review_required' => (bool) $this->settings->get('ai.human_review_required', true),
            'require_grounding' => (bool) $this->settings->get('ai.require_grounding', true),
            'require_citations' => (bool) $this->settings->get('ai.require_citations', true),
            'allow_private_provider_endpoints' => (bool) $this->settings->get('ai.allow_private_provider_endpoints', false),
            'background_inbox_analysis' => (bool) $this->settings->get('ai.background_inbox_analysis', true),
            'autonomous_replies_enabled' => (bool) $this->settings->get('ai.autonomous_replies_enabled', false),
            'log_retention_days' => (int) $this->settings->get('ai.log_retention_days', config('ai.retention.default_days', 90)),
            'monthly_token_budget' => $this->settings->get('ai.monthly_token_budget'),
            'monthly_cost_budget' => $this->settings->get('ai.monthly_cost_budget'),
        ];
    }

    /** @param array<string, mixed> $values */
    public function update(array $values, User $actor): array
    {
        $before = $this->current();
        $values['log_retention_days'] = min((int) config('ai.retention.maximum_days', 365), max(1, (int) $values['log_retention_days']));
        foreach ($values as $key => $value) {
            $this->settings->set('ai.'.$key, $value);
        }
        $this->audit->record('ai.settings_updated', $actor, 'Updated AI workspace settings',
            oldValues: $before, newValues: $values, subjectType: 'ai_settings', subjectLabel: 'AI settings');
        return $values;
    }
}
