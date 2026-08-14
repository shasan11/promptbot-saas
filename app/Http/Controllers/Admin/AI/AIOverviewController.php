<?php

namespace App\Http\Controllers\Admin\AI;

use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIPurpose;
use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use App\Services\AI\AIConfigResolver;
use App\Services\AI\AIModelResolver;
use App\Services\AI\Exceptions\AIConfigurationMissingException;
use Inertia\Inertia;
use Inertia\Response;

class AIOverviewController extends Controller
{
    public function __invoke(AIConfigResolver $config, AIModelResolver $modelResolver): Response
    {
        $providers = AiProvider::query()->orderBy('priority')->get();
        $enabledProviders = $providers->where('is_enabled', true);

        $defaultProviderId = $enabledProviders->sortBy('priority')->first()?->id;

        $defaultChatModel = $this->resolveDefault($modelResolver, AIPurpose::General, AIModelCapability::Chat);
        $defaultEmbeddingModel = $this->resolveDefault($modelResolver, AIPurpose::KnowledgeEmbedding, AIModelCapability::Embedding);

        $now = now();
        $startOfDay = $now->copy()->startOfDay();
        $startOfMonth = $now->copy()->startOfMonth();

        $monthLogs = AiUsageLog::query()->where('created_at', '>=', $startOfMonth);
        $requestsToday = AiUsageLog::query()->where('created_at', '>=', $startOfDay)->count();
        $requestsThisMonth = (clone $monthLogs)->count();
        $tokensThisMonth = (int) (clone $monthLogs)->sum('total_tokens');
        $failedThisMonth = (clone $monthLogs)->where('status', 'failed')->count();
        $avgLatencyMs = (int) round((clone $monthLogs)->whereNotNull('latency_ms')->avg('latency_ms') ?? 0);

        $hasCostConfigured = AiModel::query()
            ->where(fn ($q) => $q->where('input_cost_per_million_tokens', '>', 0)->orWhere('output_cost_per_million_tokens', '>', 0))
            ->exists();
        $estimatedCostThisMonth = $hasCostConfigured ? (float) (clone $monthLogs)->sum('estimated_cost') : null;

        return Inertia::render('Admin/AI/Overview', [
            'status' => [
                'master_enabled' => $config->isEnabled(),
                'providers_configured' => $providers->count(),
                'providers_enabled' => $enabledProviders->count(),
                'active_provider' => $enabledProviders->sortBy('priority')->first()?->name,
                'fallback_provider' => $enabledProviders->count() > 1 ? $enabledProviders->sortBy('priority')->skip(1)->first()?->name : null,
                'default_chat_model' => $defaultChatModel,
                'default_embedding_model' => $defaultEmbeddingModel,
            ],
            'metrics' => [
                'requests_today' => $requestsToday,
                'requests_this_month' => $requestsThisMonth,
                'tokens_this_month' => $tokensThisMonth,
                'estimated_cost_this_month' => $estimatedCostThisMonth,
                'failed_requests_this_month' => $failedThisMonth,
                'average_latency_ms' => $avgLatencyMs,
            ],
            'providerHealth' => $providers->map(fn (AiProvider $provider) => [
                'name' => $provider->name,
                'driver_label' => $provider->driver->label(),
                'configured' => $provider->hasKey() || $provider->driver->value === 'ollama',
                'connection_status' => $provider->last_test_status,
                'is_enabled' => $provider->is_enabled,
                'is_default' => $provider->id === $defaultProviderId,
            ])->values(),
            'warnings' => $this->warnings($config, $providers, $defaultChatModel, $defaultEmbeddingModel),
        ]);
    }

    private function resolveDefault(AIModelResolver $modelResolver, AIPurpose $purpose, AIModelCapability $capability): ?string
    {
        try {
            return $modelResolver->chainFor($purpose, $capability)->first()?->display_name;
        } catch (AIConfigurationMissingException) {
            return null;
        }
    }

    private function warnings(AIConfigResolver $config, $providers, ?string $defaultChatModel, ?string $defaultEmbeddingModel): array
    {
        $warnings = [];

        if (! $config->isEnabled()) {
            $warnings[] = 'All AI features are disabled.';

            return $warnings;
        }

        if ($providers->where('is_enabled', true)->isEmpty()) {
            $warnings[] = 'No AI provider has been configured or enabled.';
        }

        foreach ($providers->where('is_enabled', true) as $provider) {
            if (! $provider->hasKey() && $provider->driver->value !== 'ollama') {
                $warnings[] = "{$provider->name} API key has not been configured.";
            }
        }

        if (! $defaultChatModel) {
            $warnings[] = 'No chat model configured.';
        }

        if (! $defaultEmbeddingModel) {
            $warnings[] = 'No embedding model configured.';
        }

        return $warnings;
    }
}
