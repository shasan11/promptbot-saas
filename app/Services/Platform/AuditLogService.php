<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public function record(string $action, ?Model $entity = null, array $context = []): AuditLog
    {
        $request = request();

        return AuditLog::create([
            'administrator_id' => $request instanceof Request ? $request->user('central')?->id : null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'action' => $action,
            'entity_type' => $entity ? $entity::class : ($context['entity_type'] ?? null),
            'entity_id' => $entity ? (string) $entity->getKey() : ($context['entity_id'] ?? null),
            'old_values' => $this->sanitizeArray($context['old_values'] ?? []),
            'new_values' => $this->sanitizeArray($context['new_values'] ?? []),
            'reason' => $context['reason'] ?? null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'request_uuid' => $context['request_uuid'] ?? (string) Str::uuid(),
            'impersonation_session_id' => $context['impersonation_session_id'] ?? null,
            'severity' => $context['severity'] ?? 'info',
        ]);
    }

    private function sanitizeArray(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|key|authorization/i', (string) $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitizeArray($value);
            }
        }

        return $values;
    }
}
