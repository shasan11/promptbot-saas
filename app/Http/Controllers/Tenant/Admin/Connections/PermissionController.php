<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionAccessGrant;
use App\Models\Connections\ConnectionAgentAccess;
use App\Models\Connections\ConnectionResource;
use App\Models\Connections\ConnectionResourcePermission;
use App\Models\Connections\ConnectionWorkflowAccess;
use App\Services\Connections\ConnectionAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    private const ACCESS_CAPABILITIES = [
        'resources.view',
        'resources.sync',
        'actions.execute',
        'logs.view',
        'credentials.view',
    ];

    public function index(Request $request): Response
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        return Inertia::render('Tenant/Admin/Connections/Permissions/Index', [
            'connections' => Connection::query()
                ->with([
                    'integration:id,name,key,provider',
                    'actions:id,connection_id,key,name,risk_level,enabled_for_ai,enabled_for_workflows,status',
                    'triggers:id,connection_id,key,name,status',
                    'resources.permissions',
                    'agentAccess',
                    'workflowAccess',
                    'accessGrants.grantor:id,name,email',
                ])
                ->withCount(['agentAccess', 'workflowAccess', 'accessGrants'])
                ->latest()
                ->paginate(10),
            'accessCapabilities' => self::ACCESS_CAPABILITIES,
        ]);
    }

    public function storeAgent(Request $request, Connection $connection, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $data = $request->validate([
            'agent_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_.:-]*$/i'],
            'allowed_actions' => ['required', 'array', 'min:1'],
            'allowed_actions.*' => ['required', 'string', 'max:120'],
            'allowed_resources' => ['nullable', 'array'],
            'allowed_resources.*' => ['string', 'max:160'],
            'read_only' => ['nullable', 'boolean'],
            'approval_required' => ['nullable', 'boolean'],
            'rate_limit_per_hour' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $this->assertAllowedActionKeys($connection, $data['allowed_actions'], 'ai');
        $this->assertExplicitResourceKeys($data['allowed_resources'] ?? []);

        $grant = ConnectionAgentAccess::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'agent_key' => $data['agent_key'],
            ],
            [
                'tenant_id' => tenant('id'),
                'allowed_actions' => array_values(array_unique($data['allowed_actions'])),
                'allowed_resources' => array_values(array_unique($data['allowed_resources'] ?? [])),
                'read_only' => $request->boolean('read_only', true),
                'approval_required' => $request->boolean('approval_required', true),
                'rate_limit_per_hour' => $data['rate_limit_per_hour'] ?? null,
            ]
        );

        $audit->record('permissions.agent_grant_saved', $connection, $request->user('tenant'), message: 'AI agent connection access updated.', context: [
            'agent_key' => $grant->agent_key,
            'allowed_actions' => $grant->allowed_actions,
            'read_only' => $grant->read_only,
            'approval_required' => $grant->approval_required,
        ]);

        return back()->with('status', 'AI agent access saved.');
    }

    public function storeWorkflow(Request $request, Connection $connection, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $data = $request->validate([
            'workflow_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_.:-]*$/i'],
            'allowed_actions' => ['required', 'array', 'min:1'],
            'allowed_actions.*' => ['required', 'string', 'max:120'],
            'allowed_triggers' => ['nullable', 'array'],
            'allowed_triggers.*' => ['string', 'max:120'],
            'approval_required' => ['nullable', 'boolean'],
        ]);

        $this->assertAllowedActionKeys($connection, $data['allowed_actions'], 'workflow');
        $this->assertAllowedTriggerKeys($connection, $data['allowed_triggers'] ?? []);

        $grant = ConnectionWorkflowAccess::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'workflow_key' => $data['workflow_key'],
            ],
            [
                'tenant_id' => tenant('id'),
                'allowed_actions' => array_values(array_unique($data['allowed_actions'])),
                'allowed_triggers' => array_values(array_unique($data['allowed_triggers'] ?? [])),
                'approval_required' => $request->boolean('approval_required', true),
            ]
        );

        $audit->record('permissions.workflow_grant_saved', $connection, $request->user('tenant'), message: 'Workflow connection access updated.', context: [
            'workflow_key' => $grant->workflow_key,
            'allowed_actions' => $grant->allowed_actions,
            'allowed_triggers' => $grant->allowed_triggers,
            'approval_required' => $grant->approval_required,
        ]);

        return back()->with('status', 'Workflow access saved.');
    }

    public function storeAccessGrant(Request $request, Connection $connection, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['workspace', 'user', 'team', 'role'])],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['required', Rule::in(self::ACCESS_CAPABILITIES)],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($data['subject_type'] !== 'workspace' && empty($data['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => 'Select the user, team, or role this grant applies to.']);
        }

        if ($data['subject_type'] === 'workspace') {
            $data['subject_id'] = null;
        }

        $grant = ConnectionAccessGrant::updateOrCreate(
            [
                'connection_id' => $connection->id,
                'subject_type' => $data['subject_type'],
                'subject_id' => $data['subject_id'],
            ],
            [
                'tenant_id' => tenant('id'),
                'capabilities' => array_values(array_unique($data['capabilities'])),
                'granted_by' => $request->user('tenant')?->id,
                'expires_at' => $data['expires_at'] ?? null,
            ]
        );

        $audit->record('permissions.access_grant_saved', $connection, $request->user('tenant'), message: 'Connection access grant updated.', context: [
            'subject_type' => $grant->subject_type,
            'subject_id' => $grant->subject_id,
            'capabilities' => $grant->capabilities,
            'expires_at' => $grant->expires_at?->toIso8601String(),
        ]);

        return back()->with('status', 'Access grant saved.');
    }

    public function storeResourceGrant(Request $request, ConnectionResource $resource, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $data = $request->validate([
            'subject_type' => ['required', Rule::in(['workspace', 'user', 'team', 'role'])],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'capabilities' => ['required', 'array', 'min:1'],
            'capabilities.*' => ['required', Rule::in(self::ACCESS_CAPABILITIES)],
        ]);

        if ($data['subject_type'] !== 'workspace' && empty($data['subject_id'])) {
            throw ValidationException::withMessages(['subject_id' => 'Select the user, team, or role this resource grant applies to.']);
        }

        if ($data['subject_type'] === 'workspace') {
            $data['subject_id'] = null;
        }

        $resource->load('connection');

        $grant = ConnectionResourcePermission::updateOrCreate(
            [
                'connection_resource_id' => $resource->id,
                'subject_type' => $data['subject_type'],
                'subject_id' => $data['subject_id'],
            ],
            [
                'tenant_id' => tenant('id'),
                'capabilities' => array_values(array_unique($data['capabilities'])),
            ]
        );

        $audit->record('permissions.resource_grant_saved', $resource->connection, $request->user('tenant'), message: 'Resource access grant updated.', context: [
            'resource_id' => $resource->id,
            'resource_external_id' => $resource->external_id,
            'subject_type' => $grant->subject_type,
            'subject_id' => $grant->subject_id,
            'capabilities' => $grant->capabilities,
        ]);

        return back()->with('status', 'Resource grant saved.');
    }

    public function destroyAgent(Request $request, ConnectionAgentAccess $grant, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $grant->load('connection');
        $audit->record('permissions.agent_grant_deleted', $grant->connection, $request->user('tenant'), message: 'AI agent connection access removed.', context: [
            'agent_key' => $grant->agent_key,
        ], level: 'warning');
        $grant->delete();

        return back()->with('status', 'AI agent access removed.');
    }

    public function destroyWorkflow(Request $request, ConnectionWorkflowAccess $grant, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $grant->load('connection');
        $audit->record('permissions.workflow_grant_deleted', $grant->connection, $request->user('tenant'), message: 'Workflow connection access removed.', context: [
            'workflow_key' => $grant->workflow_key,
        ], level: 'warning');
        $grant->delete();

        return back()->with('status', 'Workflow access removed.');
    }

    public function destroyAccessGrant(Request $request, ConnectionAccessGrant $grant, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $grant->load('connection');
        $audit->record('permissions.access_grant_deleted', $grant->connection, $request->user('tenant'), message: 'Connection access grant removed.', context: [
            'subject_type' => $grant->subject_type,
            'subject_id' => $grant->subject_id,
        ], level: 'warning');
        $grant->delete();

        return back()->with('status', 'Access grant removed.');
    }

    public function destroyResourceGrant(Request $request, ConnectionResourcePermission $grant, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.permissions.manage'), 403);

        $grant->load('resource.connection');
        $audit->record('permissions.resource_grant_deleted', $grant->resource?->connection, $request->user('tenant'), message: 'Resource access grant removed.', context: [
            'resource_id' => $grant->connection_resource_id,
            'subject_type' => $grant->subject_type,
            'subject_id' => $grant->subject_id,
        ], level: 'warning');
        $grant->delete();

        return back()->with('status', 'Resource grant removed.');
    }

    private function assertAllowedActionKeys(Connection $connection, array $keys, string $surface): void
    {
        if (in_array('*', $keys, true)) {
            throw ValidationException::withMessages(['allowed_actions' => 'Select explicit actions instead of granting wildcard access.']);
        }

        $query = $connection->actions()->where('status', 'active');
        $query->where($surface === 'ai' ? 'enabled_for_ai' : 'enabled_for_workflows', true);
        $allowed = $query->pluck('key')->all();
        $invalid = array_values(array_diff($keys, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages(['allowed_actions' => 'One or more selected actions are not enabled for this access surface.']);
        }
    }

    private function assertAllowedTriggerKeys(Connection $connection, array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $allowed = $connection->triggers()->where('status', 'active')->pluck('key')->all();
        $invalid = array_values(array_diff($keys, $allowed));

        if ($invalid !== []) {
            throw ValidationException::withMessages(['allowed_triggers' => 'One or more selected triggers are not active on this connection.']);
        }
    }

    private function assertExplicitResourceKeys(array $keys): void
    {
        if (in_array('*', $keys, true)) {
            throw ValidationException::withMessages(['allowed_resources' => 'Select explicit resources instead of granting wildcard access.']);
        }
    }
}
