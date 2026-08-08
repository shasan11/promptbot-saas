<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionHealth;
use App\Enums\Connections\ConnectionStatus;
use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\CredentialStatus;
use App\Enums\Connections\Environment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Connections\ConnectionActionExecuteRequest;
use App\Http\Requests\Tenant\Connections\ConnectionStoreRequest;
use App\Jobs\Connections\DiscoverConnectionResourcesJob;
use App\Jobs\Connections\RunConnectionSyncJob;
use App\Jobs\Connections\TestConnectionJob;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAction;
use App\Models\Connections\ConnectionIntegration;
use App\Services\Connections\ConnectionAuditService;
use App\Services\Connections\ConnectionActionExecutionService;
use App\Services\Connections\ConnectionLifecycleService;
use App\Services\Connections\CredentialVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Inertia\Inertia;
use Inertia\Response;

class ConnectionController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.view'), 403);

        $query = Connection::query()
            ->with(['integration:id,key,name,provider,category,capabilities', 'owner:id,name,email'])
            ->withCount(['dataSources', 'resources'])
            ->latest();

        if ($search = $request->string('search')->toString()) {
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('provider_account_name', 'like', "%{$search}%"));
        }

        foreach (['status', 'health_status', 'connection_type', 'auth_type'] as $filter) {
            if ($value = $request->string($filter)->toString()) {
                $query->where($filter, $value);
            }
        }

        return Inertia::render('Tenant/Admin/Connections/Connections/Index', [
            'connections' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search', 'status', 'health_status', 'connection_type', 'auth_type']),
            'statusOptions' => array_map(fn ($case) => $case->value, ConnectionStatus::cases()),
            'healthOptions' => array_map(fn ($case) => $case->value, ConnectionHealth::cases()),
            'typeOptions' => array_map(fn ($case) => $case->value, ConnectionType::cases()),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.create'), 403);

        return Inertia::render('Tenant/Admin/Connections/Connections/Create', [
            'integrations' => ConnectionIntegration::query()->orderBy('category')->orderBy('name')->get(),
            'selectedIntegration' => $request->integer('integration') ? ConnectionIntegration::find($request->integer('integration')) : null,
            'authTypes' => array_map(fn ($case) => $case->value, AuthenticationType::cases()),
            'connectionTypes' => array_map(fn ($case) => $case->value, ConnectionType::cases()),
            'environments' => array_map(fn ($case) => $case->value, Environment::cases()),
        ]);
    }

    public function store(ConnectionStoreRequest $request, CredentialVault $vault, ConnectionAuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user('tenant');

        $connection = Connection::create([
            ...collect($data)->except('credential')->all(),
            'tenant_id' => tenant('id'),
            'status' => empty($data['credential']) && $data['auth_type'] !== AuthenticationType::None->value ? ConnectionStatus::Draft : ConnectionStatus::Active,
            'health_status' => ConnectionHealth::Unknown,
            'credential_status' => empty($data['credential']) ? CredentialStatus::Missing : CredentialStatus::Active,
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
            'owner_user_id' => $actor?->id,
            'connected_at' => empty($data['credential']) ? null : now(),
        ]);

        if (! empty($data['credential'])) {
            $vault->store($connection, AuthenticationType::from($data['auth_type']), $data['credential'], $actor, ['source' => 'connection_wizard']);
        }

        $audit->record('connection.created', $connection, $actor, message: 'Connection created.');

        return redirect()->route('tenant.admin.connections.show', $connection)->with('status', 'Connection created.');
    }

    public function show(Request $request, Connection $connection, ConnectionLifecycleService $lifecycle): Response
    {
        abort_unless($request->user('tenant')?->can('connections.view'), 403);

        return Inertia::render('Tenant/Admin/Connections/Connections/Show', [
            'deleteImpact' => $lifecycle->deletionImpact($connection),
            'connection' => $connection->load([
                'integration',
                'credentials' => fn ($query) => $query->latest(),
                'resources' => fn ($query) => $query->latest('discovered_at')->limit(20),
                'dataSources' => fn ($query) => $query->latest()->limit(20),
                'syncRuns' => fn ($query) => $query->latest()->limit(10),
                'logs' => fn ($query) => $query->latest('created_at')->limit(10),
                'healthChecks' => fn ($query) => $query->latest('checked_at')->limit(6),
                'rateLimits' => fn ($query) => $query->latest('observed_at')->limit(4),
                'actions' => fn ($query) => $query->latest()->limit(12),
                'triggers' => fn ($query) => $query->latest()->limit(8),
                'apiOperations' => fn ($query) => $query->latest()->limit(8),
                'actionExecutions' => fn ($query) => $query->with('action:id,name,key')->latest()->limit(8),
                'agentAccess' => fn ($query) => $query->latest()->limit(6),
                'workflowAccess' => fn ($query) => $query->latest()->limit(6),
                'usageRecords' => fn ($query) => $query->latest('created_at')->limit(12),
                'owner:id,name,email',
            ])->loadCount(['dataSources', 'resources', 'syncRuns', 'healthChecks', 'actions', 'triggers', 'actionExecutions']),
        ]);
    }

    public function executeAction(ConnectionActionExecuteRequest $request, Connection $connection, ConnectionAction $action, ConnectionActionExecutionService $service): RedirectResponse
    {
        abort_unless($action->connection_id === $connection->id, 404);

        $execution = $service->execute(
            $connection,
            $action,
            $request->validated('input') ?? [],
            $request->user('tenant'),
            $request->validated('agent_key'),
            $request->validated('workflow_key'),
            $request->validated('idempotency_key') ?? (string) str()->uuid(),
        );

        return back()->with($execution->status === 'failed' ? 'error' : 'status', $execution->approval_required ? 'Action is waiting for approval.' : 'Action executed.');
    }

    public function test(Request $request, Connection $connection): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.test'), 403);

        TestConnectionJob::dispatch($connection->id, $request->user('tenant')?->id);

        return back()->with('status', 'Connection test queued.');
    }

    public function discover(Request $request, Connection $connection): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.resources.discover'), 403);

        DiscoverConnectionResourcesJob::dispatch($connection->id, $request->user('tenant')?->id);

        return back()->with('status', 'Resource discovery queued.');
    }

    public function sync(Request $request, Connection $connection): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.sync.run'), 403);

        RunConnectionSyncJob::dispatch($connection->id, null, $request->user('tenant')?->id);

        return back()->with('status', 'Sync queued.');
    }

    public function disable(Request $request, Connection $connection, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.disable'), 403);

        $connection->forceFill(['status' => ConnectionStatus::Disabled, 'health_status' => ConnectionHealth::Disabled])->save();
        $audit->record('connection.disabled', $connection, $request->user('tenant'), message: 'Connection disabled.');

        return back()->with('status', 'Connection disabled.');
    }

    public function enable(Request $request, Connection $connection, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.update'), 403);

        $connection->forceFill(['status' => ConnectionStatus::Active, 'health_status' => ConnectionHealth::Unknown])->save();
        $audit->record('connection.enabled', $connection, $request->user('tenant'), message: 'Connection enabled.');

        return back()->with('status', 'Connection enabled.');
    }

    public function destroy(Request $request, Connection $connection, ConnectionLifecycleService $lifecycle): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.delete'), 403);

        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $lifecycle->deleteSafely($connection, $request->user('tenant'), $data['confirmation'], $data['reason'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tenant.admin.connections.index')->with('status', 'Connection deleted safely.');
    }
}
