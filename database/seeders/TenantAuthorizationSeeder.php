<?php

namespace Database\Seeders;

use App\Models\TenantPermission;
use App\Models\TenantRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the tenant-guard permission catalog and default roles. Idempotent —
 * safe to re-run against an existing tenant (e.g. via the "Run seeders"
 * danger-zone action) without duplicating or clobbering custom roles.
 */
class TenantAuthorizationSeeder extends Seeder
{
    /** @var array<string, array<int, string>> */
    private const PERMISSIONS = [
        'Dashboard' => ['dashboard.view'],
        'Customers' => [
            'customers.view', 'customers.create', 'customers.update', 'customers.delete',
            'customers.restore', 'customers.import', 'customers.export',
        ],
        'Companies' => [
            'companies.view', 'companies.create', 'companies.update', 'companies.delete', 'companies.restore',
        ],
        'Customer Configuration' => ['tags.manage', 'custom_fields.manage'],
        'Channels' => ['channels.view', 'channels.create', 'channels.update', 'channels.delete', 'channels.credentials.manage', 'channels.logs.view'],
        'Inbox' => ['inbox.view', 'inbox.reply', 'inbox.update', 'inbox.assign', 'inbox.bulk', 'inbox.attachments'],
        'Tickets' => ['tickets.view', 'tickets.create', 'tickets.update', 'tickets.delete', 'tickets.merge', 'tickets.bulk', 'tickets.settings.manage'],
        'Tasks' => ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.bulk'],
        'Operations' => ['operations.view', 'operations.manage', 'sla.view', 'sla.manage', 'workforce.view', 'workforce.manage'],
        'Automation & Productivity' => ['automation.view', 'automation.manage', 'productivity.manage'],
        'Customer Experience' => ['experience.view', 'experience.manage'],
        'Reporting & Governance' => ['reports.view', 'reports.export', 'governance.view', 'developer.manage', 'privacy.manage'],
        'Quality' => ['quality.view', 'quality.manage'],
        'Users' => [
            'users.view', 'users.create', 'users.update', 'users.suspend', 'users.delete',
            'users.manage_roles', 'users.manage_sessions',
        ],
        'Invitations' => ['invitations.view', 'invitations.create', 'invitations.resend', 'invitations.revoke'],
        'Teams' => ['teams.view', 'teams.create', 'teams.update', 'teams.delete', 'teams.manage_members'],
        'Departments' => ['departments.view', 'departments.create', 'departments.update', 'departments.delete'],
        'Roles & Permissions' => ['roles.view', 'roles.create', 'roles.update', 'roles.delete', 'roles.assign', 'permissions.view', 'permissions.assign'],
        'Workspace' => ['workspace.view', 'workspace.update', 'workspace.manage_branding', 'workspace.manage_localization', 'workspace.manage_business_hours'],
        'Security' => ['security.view', 'security.update', 'security.view_sessions', 'security.revoke_sessions', 'security.view_login_activity'],
        'Audit Logs' => ['audit_logs.view', 'audit_logs.export'],
        'Knowledge' => [
            'knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.delete', 'knowledge.manage',
        ],
        'Knowledge Sources' => [
            'knowledge.sources.view', 'knowledge.sources.create', 'knowledge.sources.update',
            'knowledge.sources.delete', 'knowledge.sync', 'knowledge.reindex',
        ],
        'Knowledge Documents' => [
            'knowledge.documents.upload', 'knowledge.documents.download', 'knowledge.documents.delete',
        ],
        'Knowledge Operations' => [
            'knowledge.retrieval.test', 'knowledge.analytics.view',
            'knowledge.permissions.manage', 'knowledge.settings.manage',
        ],
        'Artificial Intelligence' => [
            'ai.view', 'ai.agents.view', 'ai.agents.manage', 'ai.agents.deploy',
            'ai.playground.use', 'ai.prompts.view', 'ai.prompts.manage',
            'ai.providers.view', 'ai.providers.manage', 'ai.providers.test',
            'ai.approvals.view', 'ai.approvals.decide',
            'ai.evaluations.view', 'ai.evaluations.manage', 'ai.evaluations.run',
            'ai.usage.view', 'ai.logs.view', 'ai.settings.manage',
            'ai.copilot.use', 'ai.feedback.create',
        ],
        'Connections' => [
            'connections.view', 'connections.create', 'connections.update', 'connections.delete',
            'connections.test', 'connections.disable', 'connections.reconnect',
        ],
        'Connection Catalog' => [
            'connections.catalog.view', 'connections.catalog.manage',
        ],
        'Connection Data Sources' => [
            'connections.data_sources.view', 'connections.data_sources.create',
            'connections.data_sources.update', 'connections.data_sources.delete',
            'connections.data_sources.sync',
        ],
        'Connection Operations' => [
            'connections.resources.view', 'connections.resources.discover',
            'connections.sync.view', 'connections.sync.run', 'connections.logs.view',
            'connections.credentials.view', 'connections.credentials.manage',
            'connections.webhooks.view', 'connections.webhooks.manage',
            'connections.api.view', 'connections.api.manage',
            'connections.databases.view', 'connections.databases.manage',
            'connections.mcp.view', 'connections.mcp.manage',
            'connections.permissions.manage', 'connections.settings.manage',
            'connections.actions.view', 'connections.actions.execute', 'connections.actions.manage',
            'connections.triggers.view', 'connections.triggers.manage',
            'connections.usage.view',
        ],
    ];

    /** @var array<string, array<int, string>|'*'> */
    private const ROLES = [
        'Tenant Owner' => '*',
        'Tenant Administrator' => '*',
        'Manager' => [
            'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.manage_roles',
            'customers.view', 'customers.create', 'customers.update', 'customers.restore', 'customers.import', 'customers.export',
            'companies.view', 'companies.create', 'companies.update', 'companies.restore', 'tags.manage', 'custom_fields.manage',
            'channels.view', 'channels.create', 'channels.update', 'channels.credentials.manage', 'channels.logs.view',
            'inbox.view', 'inbox.reply', 'inbox.update', 'inbox.assign', 'inbox.bulk', 'inbox.attachments',
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.merge', 'tickets.bulk', 'tickets.settings.manage',
            'tasks.view', 'tasks.create', 'tasks.update', 'tasks.bulk',
            'operations.view', 'operations.manage', 'sla.view', 'sla.manage', 'workforce.view', 'workforce.manage',
            'automation.view', 'automation.manage', 'productivity.manage',
            'experience.view', 'experience.manage',
            'reports.view', 'reports.export', 'governance.view', 'developer.manage', 'privacy.manage',
            'quality.view', 'quality.manage',
            'invitations.view', 'invitations.create', 'invitations.resend', 'invitations.revoke',
            'teams.view', 'teams.create', 'teams.update', 'teams.manage_members',
            'departments.view', 'roles.view', 'workspace.view',
            // Managers own the knowledge their teams rely on, but not the
            // platform-level knobs (embedding provider, retrieval tuning) that
            // change cost and answer quality workspace-wide.
            'knowledge.view', 'knowledge.create', 'knowledge.update', 'knowledge.manage',
            'knowledge.sources.view', 'knowledge.sources.create', 'knowledge.sources.update',
            'knowledge.sources.delete', 'knowledge.sync', 'knowledge.reindex',
            'knowledge.documents.upload', 'knowledge.documents.download', 'knowledge.documents.delete',
            'knowledge.retrieval.test', 'knowledge.analytics.view', 'knowledge.permissions.manage',
            'ai.view', 'ai.agents.view', 'ai.agents.manage', 'ai.agents.deploy',
            'ai.playground.use', 'ai.prompts.view', 'ai.prompts.manage',
            'ai.providers.view', 'ai.providers.manage', 'ai.providers.test',
            'ai.approvals.view', 'ai.approvals.decide',
            'ai.evaluations.view', 'ai.evaluations.manage', 'ai.evaluations.run',
            'ai.usage.view', 'ai.logs.view', 'ai.settings.manage', 'ai.copilot.use', 'ai.feedback.create',
            'connections.view', 'connections.create', 'connections.update', 'connections.test',
            'connections.disable', 'connections.reconnect',
            'connections.catalog.view',
            'connections.data_sources.view', 'connections.data_sources.create',
            'connections.data_sources.update', 'connections.data_sources.sync',
            'connections.resources.view', 'connections.resources.discover',
            'connections.sync.view', 'connections.sync.run', 'connections.logs.view',
            'connections.credentials.view',
            'connections.webhooks.view', 'connections.api.view', 'connections.databases.view',
            'connections.mcp.view', 'connections.actions.view', 'connections.actions.execute',
            'connections.triggers.view', 'connections.usage.view',
        ],
        'Team Lead' => [
            'dashboard.view', 'users.view', 'teams.view', 'teams.manage_members', 'departments.view',
            'customers.view', 'customers.create', 'customers.update', 'customers.export',
            'companies.view', 'companies.create', 'companies.update',
            'channels.view',
            'inbox.view', 'inbox.reply', 'inbox.update', 'inbox.assign', 'inbox.attachments',
            'tickets.view', 'tickets.create', 'tickets.update', 'tickets.merge', 'tasks.view', 'tasks.create', 'tasks.update',
            'operations.view', 'sla.view', 'workforce.view',
            'automation.view', 'automation.manage', 'productivity.manage',
            'experience.view', 'experience.manage',
            'reports.view', 'reports.export', 'governance.view', 'developer.manage', 'privacy.manage',
            'quality.view', 'quality.manage',
            'knowledge.view', 'knowledge.create', 'knowledge.update',
            'knowledge.sources.view', 'knowledge.sources.create', 'knowledge.sources.update',
            'knowledge.documents.upload', 'knowledge.documents.download',
            'knowledge.retrieval.test', 'knowledge.analytics.view',
            'ai.view', 'ai.agents.view', 'ai.playground.use', 'ai.prompts.view',
            'ai.approvals.view', 'ai.evaluations.view',
            'ai.usage.view', 'ai.logs.view', 'ai.copilot.use', 'ai.feedback.create',
            'connections.view', 'connections.catalog.view', 'connections.data_sources.view',
            'connections.resources.view', 'connections.sync.view', 'connections.logs.view',
            'connections.actions.view', 'connections.triggers.view', 'connections.usage.view',
        ],
        'Agent' => [
            'dashboard.view', 'users.view',
            'customers.view', 'customers.create', 'customers.update', 'companies.view',
            'channels.view',
            'inbox.view', 'inbox.reply', 'inbox.update', 'inbox.attachments',
            'tickets.view', 'tickets.create', 'tickets.update', 'tasks.view', 'tasks.create', 'tasks.update',
            'operations.view', 'sla.view', 'workforce.view',
            'automation.view',
            'experience.view',
            'reports.view', 'governance.view',
            'quality.view', 'quality.manage',
            // Front-line agents read knowledge and can test deterministic
            // search results, but cannot add, change or delete any of it.
            'knowledge.view', 'knowledge.sources.view', 'knowledge.retrieval.test',
            'ai.view', 'ai.copilot.use', 'ai.feedback.create',
            'connections.view', 'connections.catalog.view', 'connections.data_sources.view',
            'connections.actions.view',
        ],
        'Viewer' => [
            'dashboard.view', 'users.view', 'teams.view', 'departments.view', 'roles.view', 'audit_logs.view',
            'customers.view', 'companies.view',
            'channels.view',
            'inbox.view',
            'tickets.view', 'tasks.view',
            'operations.view', 'sla.view', 'workforce.view',
            'automation.view',
            'experience.view',
            'reports.view', 'governance.view',
            'quality.view',
            'knowledge.view', 'knowledge.sources.view',
            'ai.view', 'ai.agents.view', 'ai.prompts.view', 'ai.evaluations.view', 'ai.usage.view',
            'connections.view', 'connections.catalog.view', 'connections.data_sources.view',
            'connections.actions.view',
        ],
    ];

    private const PROTECTED_ROLES = ['Tenant Owner', 'Tenant Administrator'];

    /** @var array<string, string> */
    private const LEGACY_ROLE_MAP = [
        'tenant_owner' => 'Tenant Owner',
        'tenant_admin' => 'Tenant Administrator',
    ];

    public function run(): void
    {
        config(['permission.table_names' => [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]]);
        app(PermissionRegistrar::class)->setRoleClass(TenantRole::class)->setPermissionClass(TenantPermission::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)->flatMap(
            fn (array $names, string $group) => collect($names)->map(fn (string $name) => [$name, $group])
        )->mapWithKeys(function (array $pair) {
            [$name, $group] = $pair;

            return [$name => TenantPermission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'tenant'],
                ['label' => ucwords(str_replace(['.', '_'], [' ', ' '], $name)), 'group' => $group]
            )];
        });

        foreach (self::ROLES as $name => $grant) {
            $role = TenantRole::updateOrCreate(
                ['name' => $name, 'guard_name' => 'tenant'],
                ['label' => $name, 'is_protected' => in_array($name, self::PROTECTED_ROLES, true)]
            );

            $grantedPermissions = $grant === '*' ? $permissions->values() : $permissions->only($grant)->values();
            $role->syncPermissions($grantedPermissions);
        }

        $this->syncLegacyAdminRolePermissions($permissions->values());
        $this->migrateLegacyRoleAssignments();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncLegacyAdminRolePermissions($permissions): void
    {
        foreach (self::LEGACY_ROLE_MAP as $legacyName => $canonicalName) {
            $legacyRole = TenantRole::query()
                ->where('name', $legacyName)
                ->where('guard_name', 'tenant')
                ->first();

            if (! $legacyRole) {
                continue;
            }

            $legacyRole->forceFill([
                'label' => $canonicalName,
                'is_protected' => true,
            ])->save();
            $legacyRole->syncPermissions($permissions);
        }
    }

    private function migrateLegacyRoleAssignments(): void
    {
        foreach (self::LEGACY_ROLE_MAP as $legacyName => $canonicalName) {
            $legacyRole = TenantRole::query()->where('name', $legacyName)->where('guard_name', 'tenant')->first();
            $canonicalRole = TenantRole::query()->where('name', $canonicalName)->where('guard_name', 'tenant')->first();

            if (! $legacyRole || ! $canonicalRole) {
                continue;
            }

            $assignments = DB::table('model_has_roles')
                ->where('role_id', $legacyRole->id)
                ->get(['model_type', 'model_id'])
                ->map(fn ($assignment) => [
                    'role_id' => $canonicalRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ])
                ->all();

            if ($assignments === []) {
                continue;
            }

            DB::table('model_has_roles')->insertOrIgnore($assignments);
        }
    }
}
