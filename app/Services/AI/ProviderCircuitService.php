<?php

namespace App\Services\AI;

use App\Enums\AI\ProviderStatus;
use App\Exceptions\AI\AIProviderException;
use App\Models\AI\ProviderConfig;

class ProviderCircuitService
{
    public function success(ProviderConfig $provider): void
    {
        $provider->forceFill(['status' => ProviderStatus::Healthy, 'consecutive_failures' => 0, 'circuit_open_until' => null, 'last_error_code' => null, 'last_error_message' => null])->save();
    }

    public function failure(ProviderConfig $provider, AIProviderException $exception): void
    {
        $failures = $provider->consecutive_failures + 1;
        $threshold = (int) config('ai.runtime.circuit_failure_threshold', 5);
        $provider->forceFill([
            'status' => $exception->safeCode === 'authentication_failed' ? ProviderStatus::AuthenticationFailed : ($exception->retryable ? ProviderStatus::Unavailable : ProviderStatus::Degraded),
            'consecutive_failures' => $failures, 'last_error_code' => $exception->safeCode,
            'last_error_message' => $exception->getMessage(),
            'circuit_open_until' => $failures >= $threshold ? now()->addSeconds((int) config('ai.runtime.circuit_cooldown_seconds', 120)) : null,
        ])->save();
    }
}
