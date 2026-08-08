<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Jobs\Connections\RunConnectionSyncJob;
use App\Models\Connections\DataSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DataSourceController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.data_sources.view'), 403);

        $query = DataSource::query()
            ->with(['connection.integration:id,name,key,provider'])
            ->latest();

        foreach (['status', 'resource_type', 'sync_mode'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }

        return Inertia::render('Tenant/Admin/Connections/DataSources/Index', [
            'dataSources' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['status', 'resource_type', 'sync_mode']),
        ]);
    }

    public function sync(Request $request, DataSource $dataSource): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.data_sources.sync'), 403);

        RunConnectionSyncJob::dispatch($dataSource->connection_id, $dataSource->id, $request->user('tenant')?->id);

        return back()->with('status', 'Data source sync queued.');
    }
}
