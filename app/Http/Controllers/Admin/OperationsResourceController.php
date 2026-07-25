<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersResourceTable;
use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use App\Models\PlatformOperation;
use App\Models\SystemHealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OperationsResourceController extends Controller
{
    use RendersResourceTable;

    public function health(): Response
    {
        return Inertia::render('Admin/Operations/Health', [
            'checks' => SystemHealthCheck::query()->latest('checked_at')->limit(50)->get(),
            'operations' => PlatformOperation::query()->latest()->limit(10)->get(),
            'backups' => BackupRecord::query()->latest()->limit(10)->get(),
            'queue' => [
                'driver' => config('queue.default'),
                'failed_jobs' => DB::table('failed_jobs')->count(),
                'pending_operations' => PlatformOperation::query()->whereIn('status', ['queued', 'running'])->count(),
            ],
        ]);
    }

    public function __invoke(Request $request, string $resource): Response
    {
        $map = [
            'webhooks' => ['Webhooks', 'gateway_webhooks', ['event_id', 'event_type', 'status', 'processed_at']],
            'backups' => ['Backups', 'backup_records', ['scope', 'tenant_id', 'status', 'disk', 'size', 'verified_at']],
            'maintenance' => ['Maintenance', 'maintenance_windows', ['title', 'status', 'starts_at', 'ends_at']],
            'failed-jobs' => ['Failed Jobs', 'failed_jobs', ['queue', 'failed_at', 'exception']],
            'queues' => ['Queues', 'platform_operations', ['type', 'status', 'progress', 'tenant_id']],
            'scheduler' => ['Scheduler', 'platform_operations', ['type', 'status', 'progress', 'created_at']],
            'api-logs' => ['API Logs', 'audit_logs', ['action', 'entity_type', 'severity', 'created_at']],
            'incidents' => ['Incidents', 'provider_health_logs', ['provider', 'status', 'message', 'checked_at']],
        ];

        abort_unless(isset($map[$resource]), 404);
        [$title, $table, $keys] = $map[$resource];

        return $this->tablePage($request, $title, $table, $this->columns($keys));
    }

    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => str($key)->headline()->toString(), 'searchable' => true])->all();
    }
}
