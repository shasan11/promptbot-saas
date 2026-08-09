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
        $retryAfterUntil = $this->retryAfterUntil($headers);
        $backoffUntil = $retryAfterUntil ?? ($remaining === 0 ? ($reset ?? now()->addMinute()) : null);

        $rateLimit = ProviderRateLimit::updateOrCreate(
            ['connection_id' => $connection->id, 'provider' => $connection->integration?->provider ?? 'unknown', 'bucket' => $bucket],
            [
                'tenant_id' => tenant('id'),
                'limit' => $limit,
                'remaining' => $remaining,
                'resets_at' => $reset,
                'backoff_until' => $backoffUntil,
                'headers' => app(SecretRedactor::class)->redact($headers),
                'observed_at' => now(),
            ]
        );

        if ($backoffUntil?->isFuture()) {
            $connection->forceFill([
                'status' => ConnectionStatus::RateLimited,
                'health_status' => ConnectionHealth::RateLimited,
                'last_error_at' => now(),
                'last_error_code' => 'RATE_LIMITED',
                'last_error_message' => 'Provider rate limit reached. Syncs are paused until '.$backoffUntil->toDateTimeString().'.',
            ])->save();
        }

        return $rateLimit;
    }

    public function activeBackoff(Connection $connection, string $bucket = 'default'): ?ProviderRateLimit
    {
        return ProviderRateLimit::query()
            ->where('connection_id', $connection->id)
            ->where('bucket', $bucket)
            ->whereNotNull('backoff_until')
            ->where('backoff_until', '>', now())
            ->latest('observed_at')
            ->first();
    }

    public function backoffSeconds(ProviderRateLimit $rateLimit): int
    {
        if (! $rateLimit->backoff_until || $rateLimit->backoff_until->isPast()) {
            return 0;
        }

        return max(1, now()->diffInSeconds($rateLimit->backoff_until));
    }

    private function headerInt(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $value = $this->headerValue($headers, $name);
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    private function headerDate(array $headers, array $names): ?Carbon
    {
        foreach ($names as $name) {
            $value = $this->headerValue($headers, $name);

            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if (is_string($value) && trim($value) !== '') {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function retryAfterUntil(array $headers): ?Carbon
    {
        $value = $this->headerValue($headers, 'retry-after');

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return now()->addSeconds(max(0, (int) $value));
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function headerValue(array $headers, string $name): mixed
    {
        $target = strtolower($name);

        foreach ($headers as $header => $value) {
            if (strtolower((string) $header) !== $target) {
                continue;
            }

            if (is_array($value)) {
                return $value[0] ?? null;
            }

            return $value;
        }

        return null;
    }
}
