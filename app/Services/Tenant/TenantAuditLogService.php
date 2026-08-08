<?php

namespace App\Services\Tenant;

use App\Models\TenantActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Tenant-scoped equivalent of App\Services\Platform\AuditLogService, writing
 * to the existing `activity_logs` table rather than a new one. Records are
 * append-only from the tenant UI's perspective — no update/delete action is
 * ever exposed to controllers.
 */
class TenantAuditLogService
{
    public function record(
        string $event,
        ?User $actor = null,
        ?string $description = null,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $subjectType = null,
        ?string $subjectLabel = null,
        array $metadata = [],
    ): TenantActivityLog {
        $request = request();
        $actor ??= $request instanceof Request ? $request->user('tenant') : null;

        return TenantActivityLog::create([
            'user_id' => $actor?->id,
            'actor_name' => $actor?->name,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : $subjectType,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subjectLabel,
            'description' => $description,
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
            'properties' => $this->sanitize($metadata) ?: null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? $request->userAgent() : null,
            'request_id' => (string) Str::uuid(),
        ]);
    }

    private function sanitize(array $values): array
    {
        foreach ($values as $key => $value) {
            if (preg_match('/password|token|secret|key|authorization|session/i', (string) $key)) {
                $values[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $values[$key] = $this->sanitize($value);
            }
        }

        return $values;
    }
}
