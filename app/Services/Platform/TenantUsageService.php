<?php

namespace App\Services\Platform;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class TenantUsageService
{
    public function snapshot(Tenant $tenant, string $period = 'current_month', ?string $feature = null): array
    {
        if (! $tenant->tenancy_db_name) {
            return ['available' => false, 'message' => 'Usage is unavailable until workspace provisioning completes.', 'metrics' => []];
        }

        try {
            tenancy()->initialize($tenant);
            $limits = $tenant->plan?->resource_limits ?? [];
            $metrics = collect([
                $this->countMetric('users', 'Users', $tenant->plan?->user_limit),
                $this->periodCountMetric('messages', 'Messages', $this->limit($limits, ['messages_per_month', 'messages']), $period),
                $this->countMetric('knowledge_documents', 'Knowledge documents', $this->limit($limits, ['knowledge_documents', 'knowledge'])),
                $this->countMetric('automation_rules', 'Automations', $this->limit($limits, ['automations', 'automation_rules'])),
                $this->periodCountMetric('api_request_logs', 'API requests', $this->limit($limits, ['api_requests_per_month', 'api_requests']), $period),
                $this->sumMetric('knowledge_usage_records', 'quantity', 'AI usage', $this->limit($limits, ['embedding_tokens_per_month', 'ai_usage']), 'tokens', true, 1, $period),
                $this->sumMetric('conversation_attachments', 'file_size', 'Storage', $tenant->plan?->storage_limit_mb, 'MB', false, 1048576, $period),
            ])->filter()->when($feature, fn ($items) => $items->where('key', $feature))->values()->all();

            return ['available' => true, 'message' => null, 'period' => $period, 'metrics' => $metrics];
        } catch (Throwable $exception) {
            report($exception);
            return ['available' => false, 'message' => 'Usage counters are temporarily unavailable for this workspace.', 'metrics' => []];
        } finally {
            if (tenancy()->initialized) tenancy()->end();
        }
    }

    private function countMetric(string $table, string $label, mixed $limit): ?array
    {
        if (! Schema::connection('tenant')->hasTable($table)) return null;
        return $this->metric($table, $label, (float) DB::connection('tenant')->table($table)->count(), $limit, 'count');
    }

    private function periodCountMetric(string $table, string $label, mixed $limit, string $period): ?array
    {
        if (! Schema::connection('tenant')->hasTable($table)) return null;
        $query = DB::connection('tenant')->table($table);
        if (($start = $this->periodStart($period)) && Schema::connection('tenant')->hasColumn($table, 'created_at')) $query->where('created_at', '>=', $start);
        return $this->metric($table, $label, (float) $query->count(), $limit, 'count/month');
    }

    private function sumMetric(string $table, string $column, string $label, mixed $limit, string $unit, bool $periodic, float $divisor = 1, string $period = 'current_month'): ?array
    {
        if (! Schema::connection('tenant')->hasTable($table) || ! Schema::connection('tenant')->hasColumn($table, $column)) return null;
        $query = DB::connection('tenant')->table($table);
        $dateColumn = Schema::connection('tenant')->hasColumn($table, 'usage_date') ? 'usage_date' : 'created_at';
        if ($periodic && ($start = $this->periodStart($period)) && Schema::connection('tenant')->hasColumn($table, $dateColumn)) $query->where($dateColumn, '>=', $start);
        return $this->metric($table, $label, round((float) $query->sum($column) / $divisor, 2), $limit, $unit);
    }

    private function metric(string $key, string $label, float $used, mixed $limit, string $unit): array
    {
        $limit = is_numeric($limit) && (float) $limit > 0 ? (float) $limit : null;
        $percentage = $limit ? round(($used / $limit) * 100, 1) : null;
        return ['key' => $key, 'label' => $label, 'used' => $used, 'limit' => $limit, 'unit' => $unit,
            'percentage' => $percentage, 'status' => $percentage === null ? 'normal' : ($percentage >= 200 ? 'unusually_high' : ($percentage >= 100 ? 'exceeded' : ($percentage >= 80 ? 'near_limit' : 'normal')))];
    }

    private function limit(array $limits, array $keys): mixed
    {
        foreach ($keys as $key) if (array_key_exists($key, $limits)) return $limits[$key];
        return null;
    }

    private function periodStart(string $period): mixed
    {
        return match ($period) {
            'today' => now()->startOfDay(),
            'last_30_days' => now()->subDays(30),
            'lifetime' => null,
            default => now()->startOfMonth(),
        };
    }
}
