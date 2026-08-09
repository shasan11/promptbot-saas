<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\SyncStatus;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionCredential;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\SyncRun;
use App\Models\Connections\WebhookEndpoint;
use App\Models\Connections\WebhookEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function api(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.api.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/API/Index', [
            'connections' => Connection::query()
                ->with([
                    'integration:id,name,key,provider,capabilities',
                    'apiOperations' => fn ($query) => $query->latest(),
                ])
                ->where('connection_type', 'api')
                ->latest()
                ->paginate(15),
            'riskLevels' => ['low', 'medium', 'high', 'critical'],
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
        ]);
    }

    public function databases(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.databases.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Databases/Index', [
            'connections' => Connection::query()
                ->with([
                    'integration:id,name,key,provider,capabilities',
                    'dataSources' => fn ($query) => $query
                        ->with('databaseConfig')
                        ->whereIn('resource_type', ['database_table', 'database_view'])
                        ->latest(),
                ])
                ->where('connection_type', 'database')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function webhooks(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.webhooks.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Webhooks/Index', [
            'endpoints' => WebhookEndpoint::query()->withCount('events')->latest()->paginate(15),
            'events' => WebhookEvent::query()->with(['endpoint:id,name,provider', 'attempts' => fn ($query) => $query->latest('attempted_at')->limit(3)])->latest('received_at')->limit(10)->get(),
        ]);
    }

    public function mcp(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.mcp.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/MCP/Index', [
            'connections' => Connection::query()
                ->with([
                    'integration:id,name,key,provider,capabilities',
                    'mcpTools' => fn ($query) => $query->orderByDesc('enabled_for_ai')->orderBy('risk_level')->orderBy('name'),
                    'resources' => fn ($query) => $query->latest('discovered_at')->limit(8),
                ])
                ->withCount([
                    'mcpTools',
                    'mcpTools as enabled_mcp_tools_count' => fn ($query) => $query->where(function ($query): void {
                        $query->where('enabled_for_ai', true)->orWhere('enabled_for_workflows', true);
                    }),
                    'resources',
                ])
                ->where('connection_type', 'mcp_server')
                ->latest()
                ->paginate(15),
            'riskLevels' => ['low', 'medium', 'high', 'critical'],
        ]);
    }

    public function syncJobs(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.sync.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/SyncJobs/Index', [
            'syncRuns' => SyncRun::query()->with(['connection.integration:id,name,key', 'dataSource:id,name'])->latest()->paginate(15),
        ]);
    }

    public function logs(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.logs.view'), 403);

        $query = ConnectionLog::query()
            ->with('connection.integration:id,name,key')
            ->when($request->integer('connection'), fn ($query, int $connectionId) => $query->where('connection_id', $connectionId))
            ->latest('created_at');

        return Inertia::render('Tenant/Admin/Connections/Logs/Index', [
            'logs' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only('connection'),
        ]);
    }

    public function failed(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.view'), 403);

        $failedSyncStatuses = [
            SyncStatus::Failed->value,
            SyncStatus::CompletedWithErrors->value,
            SyncStatus::RateLimited->value,
            SyncStatus::WaitingForAuth->value,
        ];

        return Inertia::render('Tenant/Admin/Connections/Failed/Index', [
            'connections' => Connection::query()
                ->with([
                    'integration:id,name,key,provider',
                    'latestFailedSyncRun',
                    'latestSuccessfulSyncRun',
                ])
                ->withCount([
                    'syncRuns as failed_sync_runs_count' => fn ($query) => $query->whereIn('status', $failedSyncStatuses),
                ])
                ->whereIn('health_status', [
                    ConnectionHealth::NeedsAttention->value,
                    ConnectionHealth::AuthenticationExpired->value,
                    ConnectionHealth::RateLimited->value,
                    ConnectionHealth::Error->value,
                ])
                ->latest('last_error_at')
                ->paginate(15),
        ]);
    }

    public function credentials(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.credentials.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Credentials/Index', [
            'credentials' => ConnectionCredential::query()->with('connection.integration:id,name,key,provider')->latest()->paginate(15),
        ]);
    }

    public function settings(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.settings.manage'), 403);

        return Inertia::render('Tenant/Admin/Connections/Settings/Index', [
            'defaults' => [
                'sync_frequency' => 'manual',
                'retention_policy' => 'retain_historical_data',
                'database_read_only_default' => true,
                'dangerous_action_approval' => true,
                'secret_redaction' => true,
            ],
        ]);
    }

    private function connectionsByType(Request $request, string $permission, string $type, string $component): Response
    {
        abort_unless($request->user('tenant')?->can($permission), 403);

        return Inertia::render($component, [
            'connections' => Connection::query()->with('integration:id,name,key,provider,capabilities')->where('connection_type', $type)->latest()->paginate(15),
        ]);
    }
}
