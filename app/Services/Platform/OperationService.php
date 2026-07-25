<?php

namespace App\Services\Platform;

use App\Models\PlatformOperation;
use App\Models\Tenant;
use Illuminate\Support\Str;

class OperationService
{
    public function create(string $type, ?Tenant $tenant = null, array $context = []): PlatformOperation
    {
        $idempotencyKey = $context['idempotency_key'] ?? $this->idempotencyKey($type, $tenant?->id, $context);

        return PlatformOperation::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'type' => $type,
                'status' => 'queued',
                'progress' => 0,
                'requested_by' => request()->user('central')?->id,
                'tenant_id' => $tenant?->id,
                'reason' => $context['reason'] ?? null,
                'metadata' => $this->sanitize($context['metadata'] ?? []),
            ],
        );
    }

    public function markRunning(PlatformOperation $operation): void
    {
        $operation->forceFill(['status' => 'running', 'started_at' => now(), 'progress' => max($operation->progress, 5)])->save();
    }

    public function progress(PlatformOperation $operation, int $progress, string $message): void
    {
        $logs = $operation->logs ?? [];
        $logs[] = ['at' => now()->toISOString(), 'message' => $this->sanitizeString($message)];

        $operation->forceFill([
            'progress' => max(0, min(100, $progress)),
            'logs' => $logs,
        ])->save();
    }

    public function complete(PlatformOperation $operation): void
    {
        $operation->forceFill(['status' => 'completed', 'progress' => 100, 'completed_at' => now()])->save();
    }

    public function fail(PlatformOperation $operation, string $message, array $context = []): void
    {
        $operation->forceFill([
            'status' => 'failed',
            'failure_message' => $this->sanitizeString($message),
            'failure_context' => $this->sanitize($context),
            'completed_at' => now(),
        ])->save();
    }

    private function idempotencyKey(string $type, ?string $tenantId, array $context): string
    {
        return hash('sha256', $type.'|'.($tenantId ?? 'platform').'|'.($context['reason'] ?? '').'|'.Str::uuid());
    }

    private function sanitize(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/password|token|secret|key|authorization/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->sanitize($value);
            }
        }

        return $context;
    }

    private function sanitizeString(string $value): string
    {
        return preg_replace('/(password|token|secret|key)=([^\\s&]+)/i', '$1=[redacted]', $value) ?? '[redacted]';
    }
}
