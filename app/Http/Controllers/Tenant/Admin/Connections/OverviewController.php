<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\SyncStatus;
use App\Http\Controllers\Controller;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionIntegration;
use App\Models\Connections\ConnectionLog;
use App\Models\Connections\DataSource;
use App\Models\Connections\SyncRun;
use App\Models\Connections\WebhookEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.view'), 403);

        $latestSuccess = SyncRun::query()
            ->where('status', SyncStatus::Completed)
            ->latest('completed_at')
            ->value('completed_at');

        return Inertia::render('Tenant/Admin/Connections/Overview', [
            'metrics' => [
                'totalConnections' => Connection::query()->count(),
                'activeConnections' => Connection::query()->where('status', ConnectionStatus::Active)->count(),
                'needsAttention' => Connection::query()->whereIn('status', [ConnectionStatus::NeedsAttention, ConnectionStatus::AuthenticationRequired, ConnectionStatus::Degraded, ConnectionStatus::RateLimited, ConnectionStatus::Error])->count(),
                'dataSources' => DataSource::query()->count(),
                'scheduledSyncs' => DataSource::query()->whereNotNull('next_sync_at')->count(),
                'failedSyncs' => SyncRun::query()->whereIn('status', [SyncStatus::Failed, SyncStatus::CompletedWithErrors, SyncStatus::RateLimited, SyncStatus::WaitingForAuth])->count(),
                'apiRequestsToday' => SyncRun::query()->whereDate('created_at', today())->sum('api_requests'),
                'webhookEventsToday' => WebhookEvent::query()->whereDate('received_at', today())->count(),
                'connectedApplications' => Connection::query()->distinct('connection_integration_id')->count('connection_integration_id'),
                'lastSuccessfulSync' => $latestSuccess,
            ],
            'healthSummary' => collect(ConnectionHealth::cases())->map(fn ($health) => [
                'status' => $health->value,
                'count' => Connection::query()->where('health_status', $health)->count(),
            ])->values(),
            'recentActivity' => ConnectionLog::query()
                ->with('connection.integration:id,name,key,provider')
                ->latest('created_at')
                ->limit(8)
                ->get(),
            'issues' => Connection::query()
                ->with('integration:id,name,key,provider')
                ->whereIn('health_status', [ConnectionHealth::NeedsAttention, ConnectionHealth::AuthenticationExpired, ConnectionHealth::RateLimited, ConnectionHealth::Error])
                ->latest('last_error_at')
                ->limit(6)
                ->get(),
            'catalogHighlights' => ConnectionIntegration::query()
                ->withCount('connections')
                ->orderByDesc('connections_count')
                ->limit(6)
                ->get(),
        ]);
    }
}
