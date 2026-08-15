<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIProviderException;
use App\Models\AI\UsageLog;
use App\Services\SaaS\TenantFeatureService;

class AIBudgetService
{
    public function __construct(
        private readonly AISettingsService $settings,
        private readonly TenantFeatureService $features,
    ) {}

    public function ensureAvailable(): void
    {
        $settings = $this->settings->current();
        if (! config('ai.enabled') || ! $settings['enabled']) {
            throw new AIProviderException('ai_disabled', 'AI is disabled for this workspace.');
        }
        if (! $this->features->enabled('ai_platform')) {
            throw new AIProviderException('feature_unavailable', 'AI is not included in this workspace plan.');
        }

        $workspaceBudget = $settings['monthly_token_budget'];
        $planBudget = $this->features->limit('ai_monthly_tokens');
        $limits = array_values(array_filter([(int) $workspaceBudget, (int) $planBudget], fn (int $value) => $value > 0));
        $usage = UsageLog::query()->where('occurred_at', '>=', now()->startOfMonth());
        $used = (int) (clone $usage)
            ->selectRaw('COALESCE(SUM(COALESCE(input_tokens,0) + COALESCE(output_tokens,0)),0) as aggregate')->value('aggregate');
        if ($limits !== [] && $used >= min($limits)) {
            throw new AIProviderException('budget_exceeded', 'The workspace monthly AI token budget has been reached.');
        }

        $costBudget = (float) ($settings['monthly_cost_budget'] ?? 0);
        if ($costBudget > 0) {
            $cost = (float) (clone $usage)->where('currency', 'USD')->sum('estimated_cost');
            if ($cost >= $costBudget) {
                throw new AIProviderException('budget_exceeded', 'The workspace monthly AI cost budget has been reached.');
            }
        }
    }
}
