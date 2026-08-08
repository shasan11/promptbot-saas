<?php

namespace App\Services\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ProviderRateLimit;
use Illuminate\Support\Carbon;

class ProviderRateLimitService
{
    public function record(Connection $connection, array $headers, string $bucket = 'default'): ProviderRateLimit
    {
        $limit = $this->headerInt($headers, ['x-ratelimit-limit', 'x-rate-limit-limit']);
        $remaining = $this->headerInt($headers, ['x-ratelimit-remaining', 'x-rate-limit-remaining']);
        $reset = $this->headerDate($headers, ['x-ratelimit-reset', 'x-rate-limit-reset']);
        $retryAfter = $this->headerInt($headers, ['retry-after']);

        $rateLimit = ProviderRateLimit::updateOrCreate(
            ['connection_id' => $connection->id, 'provider' => $connection->integration?->provider ?? 'unknown', 'bucket' => $bucket],
            [
                'tenant_id' => tenant('id'),
                'limit' => $limit,
                'remaining' => $remaining,
                'resets_at' => $reset,
                'backoff_until' => $retryAfter ? now()->addSeconds($retryAfter) : null,
                'headers' => app(SecretRedactor::class)->redact($headers),
                'observed_at' => now(),
            ]
        );

        if ($retryAfter || $remaining === 0) {
            $connection->forceFill(['status' => ConnectionStatus::RateLimited, 'health_status' => ConnectionHealth::RateLimited])->save();
        }

        return $rateLimit;
    }

    private function headerInt(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $value = $headers[$name] ?? $headers[strtolower($name)] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function headerDate(array $headers, array $names): ?Carbon
    {
        $value = $this->headerInt($headers, $names);

        return $value ? Carbon::createFromTimestamp($value) : null;
    }
}
