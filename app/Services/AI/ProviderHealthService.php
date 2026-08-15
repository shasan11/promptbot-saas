<?php

namespace App\Services\AI;

use App\Enums\AI\ProviderStatus;
use App\Exceptions\AI\AIProviderException;
use App\Models\AI\ProviderConfig;
use NeuronAI\Chat\Messages\UserMessage;
use Throwable;

class ProviderHealthService
{
    public function __construct(
        private readonly ProviderResolverService $resolver,
        private readonly ProviderErrorClassifier $errors,
    ) {}

    /** @return array{ok: bool, message: string, latency_ms: int|null} */
    public function test(ProviderConfig $config): array
    {
        $started = hrtime(true);

        try {
            $reply = $this->resolver->resolve($config, forHealthCheck: true)
                ->chat(new UserMessage('Reply with exactly OK.'));
            $latency = (int) round((hrtime(true) - $started) / 1_000_000);
            if (trim((string) $reply->getContent()) === '') {
                throw new AIProviderException('empty_response', 'The provider returned an empty response.');
            }

            $config->forceFill([
                'status' => ProviderStatus::Healthy,
                'last_tested_at' => now(), 'last_successful_test_at' => now(),
                'last_test_status' => 'passed', 'last_error_code' => null, 'last_error_message' => null,
                'consecutive_failures' => 0, 'circuit_open_until' => null,
            ])->save();

            return ['ok' => true, 'message' => 'Provider connection succeeded.', 'latency_ms' => $latency];
        } catch (Throwable $exception) {
            $safe = $this->errors->classify($exception);
            $failures = $config->consecutive_failures + 1;
            $threshold = (int) config('ai.runtime.circuit_failure_threshold', 5);
            $config->forceFill([
                'status' => $safe->safeCode === 'authentication_failed'
                    ? ProviderStatus::AuthenticationFailed
                    : ($safe->retryable ? ProviderStatus::Unavailable : ProviderStatus::Degraded),
                'last_tested_at' => now(), 'last_test_status' => 'failed',
                'last_error_code' => $safe->safeCode, 'last_error_message' => $safe->getMessage(),
                'consecutive_failures' => $failures,
                'circuit_open_until' => $failures >= $threshold ? now()->addSeconds((int) config('ai.runtime.circuit_cooldown_seconds', 120)) : null,
            ])->save();

            return ['ok' => false, 'message' => $safe->getMessage(), 'latency_ms' => null];
        }
    }
}
