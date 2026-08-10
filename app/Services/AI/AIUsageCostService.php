<?php

namespace App\Services\AI;

use App\Models\AI\ProviderConfig;

class AIUsageCostService
{
    /** @return array{cost:?float,currency:?string} */
    public function estimate(
        ProviderConfig $provider,
        string $model,
        ?int $inputTokens,
        ?int $outputTokens,
        ?int $cachedTokens = null,
        ?int $reasoningTokens = null,
    ): array {
        $configuration = (array) $provider->configuration;
        $prices = $configuration['pricing']['models'][$model] ?? null;
        if (! is_array($prices) || ! isset($prices['input_per_million'], $prices['output_per_million'])) {
            return ['cost' => null, 'currency' => null];
        }

        $input = max(0, (int) $inputTokens);
        $output = max(0, (int) $outputTokens);
        $cached = min($input, max(0, (int) $cachedTokens));
        $reasoning = min($output, max(0, (int) $reasoningTokens));
        $inputRate = (float) $prices['input_per_million'];
        $outputRate = (float) $prices['output_per_million'];
        $cachedRate = (float) ($prices['cached_input_per_million'] ?? $inputRate);
        $reasoningRate = (float) ($prices['reasoning_per_million'] ?? $outputRate);

        $cost = (($input - $cached) * $inputRate
            + $cached * $cachedRate
            + ($output - $reasoning) * $outputRate
            + $reasoning * $reasoningRate) / 1_000_000;

        return [
            'cost' => round($cost, 8),
            'currency' => strtoupper((string) ($prices['currency'] ?? 'USD')),
        ];
    }
}
