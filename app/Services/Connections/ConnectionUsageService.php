<?php

namespace App\Services\Connections;

use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionActionExecution;
use App\Models\Connections\ConnectionUsageRecord;
use App\Models\Connections\DataSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ConnectionUsageService
{
    public function record(
        string $usageType,
        int $quantity = 1,
        string $unit = 'count',
        int $bytes = 0,
        ?Connection $connection = null,
        ?DataSource $dataSource = null,
        ?ConnectionActionExecution $execution = null,
        array $metadata = [],
    ): ConnectionUsageRecord {
        return ConnectionUsageRecord::create([
            'tenant_id' => tenant('id'),
            'connection_id' => $connection?->id,
            'data_source_id' => $dataSource?->id,
            'connection_action_execution_id' => $execution?->id,
            'usage_type' => $usageType,
            'quantity' => $quantity,
            'unit' => $unit,
            'bytes' => $bytes,
            'metadata' => app(SecretRedactor::class)->redact($metadata),
            'usage_date' => today(),
            'created_at' => now(),
        ]);
    }

    public function summary(Connection $connection, ?string $from = null, ?string $to = null): array
    {
        [$fromDate, $toDate] = $this->dateRange($from, $to);
        $records = $connection->usageRecords()
            ->whereBetween('usage_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderBy('usage_date')
            ->get();

        return [
            'connection' => [
                'id' => $connection->id,
                'name' => $connection->name,
                'integration' => $connection->integration?->only(['id', 'key', 'name', 'provider']),
            ],
            'range' => [
                'from' => $fromDate->toDateString(),
                'to' => $toDate->toDateString(),
            ],
            'totals' => $this->totals($records),
            'by_usage_type' => $this->byUsageType($records),
            'by_day' => $this->byDay($records),
            'billing_categories' => $this->billingCategories($records),
            'recent_events' => $records
                ->sortByDesc('created_at')
                ->take(20)
                ->values()
                ->map(fn (ConnectionUsageRecord $record): array => $this->eventPayload($record))
                ->all(),
        ];
    }

    private function dateRange(?string $from, ?string $to): array
    {
        $toDate = $to ? CarbonImmutable::parse($to)->endOfDay() : CarbonImmutable::now()->endOfDay();
        $fromDate = $from ? CarbonImmutable::parse($from)->startOfDay() : $toDate->subDays(29)->startOfDay();

        if ($fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate->startOfDay(), $fromDate->endOfDay()];
        }

        if ($fromDate->diffInDays($toDate) > 366) {
            $fromDate = $toDate->subDays(366)->startOfDay();
        }

        return [$fromDate, $toDate];
    }

    private function totals(Collection $records): array
    {
        return [
            'events' => $records->count(),
            'quantity' => (int) $records->sum('quantity'),
            'bytes' => (int) $records->sum('bytes'),
        ];
    }

    private function byUsageType(Collection $records): array
    {
        return $records
            ->groupBy('usage_type')
            ->map(fn (Collection $group): array => [
                'events' => $group->count(),
                'quantity' => (int) $group->sum('quantity'),
                'unit' => $group->first()?->unit ?? 'count',
                'bytes' => (int) $group->sum('bytes'),
            ])
            ->sortKeys()
            ->all();
    }

    private function byDay(Collection $records): array
    {
        return $records
            ->groupBy(fn (ConnectionUsageRecord $record): string => $record->usage_date->toDateString())
            ->map(fn (Collection $group, string $date): array => [
                'date' => $date,
                'events' => $group->count(),
                'quantity' => (int) $group->sum('quantity'),
                'bytes' => (int) $group->sum('bytes'),
                'usage_types' => $this->byUsageType($group),
            ])
            ->values()
            ->all();
    }

    private function billingCategories(Collection $records): array
    {
        $categories = [
            'ai_action_usage' => ['action_execution', 'mcp_tool_execution'],
            'workflow_usage' => ['workflow_action', 'workflow_trigger'],
            'knowledge_base_usage' => ['sync_items', 'sync_bytes', 'knowledge_sync'],
            'api_request_usage' => ['api_request', 'api_operation'],
            'premium_connector_usage' => ['premium_connector'],
        ];

        return collect($categories)
            ->map(fn (array $types): array => $this->totals($records->whereIn('usage_type', $types)))
            ->all();
    }

    private function eventPayload(ConnectionUsageRecord $record): array
    {
        return [
            'id' => $record->id,
            'usage_type' => $record->usage_type,
            'quantity' => $record->quantity,
            'unit' => $record->unit,
            'bytes' => $record->bytes,
            'usage_date' => $record->usage_date->toDateString(),
            'metadata' => $record->metadata ?? [],
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }
}
