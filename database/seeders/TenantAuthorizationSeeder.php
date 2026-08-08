<?php

namespace Database\Seeders;

use App\Models\TenantPermission;
use App\Models\TenantRole;
use Illuminate\Database\Seeder;
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
    ];

    /** @var array<string, array<int, string>|'*'> */
    private const ROLES = [
        'Tenant Owner' => '*',
        'Tenant Administrator' => '*',
        'Manager' => [
            'dashboard.view', 'users.view', 'users.create', 'users.update', 'users.manage_roles',
            'invitations.view', 'invitations.create', 'invitations.resend', 'invitations.revoke',
            'teams.view', 'teams.create', 'teams.update', 'teams.manage_members',
            'departments.view', 'roles.view', 'workspace.view',
        ],
        'Team Lead' => [
            'dashboard.view', 'users.view', 'teams.view', 'teams.manage_members', 'departments.view',
        ],
        'Agent' => ['dashboard.view', 'users.view'],
        'Viewer' => ['dashboard.view', 'users.view', 'teams.view', 'departments.view', 'roles.view', 'audit_logs.view'],
    ];

    private const PROTECTED_ROLES = ['Tenant Owner', 'Tenant Administrator'];

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
            $role = TenantRole::firstOrCreate(
                ['name' => $name, 'guard_name' => 'tenant'],
                ['label' => $name, 'is_protected' => in_array($name, self::PROTECTED_ROLES, true)]
            );

            $grantedPermissions = $grant === '*' ? $permissions->values() : $permissions->only($grant)->values();
            $role->syncPermissions($grantedPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
