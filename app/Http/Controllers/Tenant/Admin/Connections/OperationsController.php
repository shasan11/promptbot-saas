<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
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
        return $this->connectionsByType($request, 'connections.api.view', 'api', 'Tenant/Admin/Connections/API/Index');
    }

    public function databases(Request $request): Response
    {
        return $this->connectionsByType($request, 'connections.databases.view', 'database', 'Tenant/Admin/Connections/Databases/Index');
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
        return $this->connectionsByType($request, 'connections.mcp.view', 'mcp_server', 'Tenant/Admin/Connections/MCP/Index');
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

        return Inertia::render('Tenant/Admin/Connections/Logs/Index', [
            'logs' => ConnectionLog::query()->with('connection.integration:id,name,key')->latest('created_at')->paginate(20),
        ]);
    }

    public function failed(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Failed/Index', [
            'connections' => Connection::query()->with('integration:id,name,key,provider')->whereIn('health_status', ['needs_attention', 'authentication_expired', 'rate_limited', 'error'])->latest('last_error_at')->paginate(15),
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
