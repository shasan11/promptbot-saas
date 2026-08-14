<?php

namespace App\Services\AI;

use App\Enums\AI\AIModelCapability;
use App\Enums\AI\AIPurpose;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiUsageLog;
use Illuminate\Support\Str;

class AIUsageTracker
{
    public function __construct(private readonly AIConfigResolver $config) {}

    public function record(
        AIPurpose $purpose,
        AIModelCapability $capability,
        ?AiProvider $provider,
        ?AiModel $model,
        string $status,
        ?string $errorCode = null,
        int $promptTokens = 0,
        int $completionTokens = 0,
        float $estimatedCost = 0.0,
        int $latencyMs = 0,
    ): void {
        if ($status === 'success' && $provider) {
            $provider->forceFill(['last_success_at' => now()])->saveQuietly();
        }

        if (! $this->config->isLoggingEnabled()) {
            return;
        }

        AiUsageLog::create([
            'tenant_id' => tenant('id') ?? null,
            'ai_provider_id' => $provider?->id,
            'provider_driver' => $provider?->driver?->value,
            'provider_name' => $provider?->name,
            'ai_model_id' => $model?->id,
            'model_key' => $model?->model_key,
            'purpose' => $purpose->value,
            'capability' => $capability->value,
            'status' => $status,
            'error_code' => $errorCode,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'estimated_cost' => $estimatedCost,
            'latency_ms' => $latencyMs,
            'request_uuid' => (string) Str::uuid(),
        ]);
    }
}
