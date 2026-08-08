<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Models\TenantPermission;
use App\Models\TenantRole;
use App\Services\Tenant\TenantAuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly TenantAuditLogService $auditLog) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', TenantRole::class);

        return Inertia::render('Tenant/Admin/Administration/Roles/Index', [
            'roles' => TenantRole::query()->withCount(['users', 'permissions'])->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TenantRole::class);

        return Inertia::render('Tenant/Admin/Administration/Roles/Edit', [
            'role' => null,
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', TenantRole::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->where('guard_name', 'tenant')],
            'label' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = TenantRole::create(['name' => $data['name'], 'label' => $data['label'] ?? $data['name'], 'guard_name' => 'tenant']);
        $role->syncPermissions($this->resolveWithDependencies($data['permissions'] ?? []));

        $this->auditLog->record('role.created', $request->user('tenant'), "Created role \"{$role->name}\"", $role);

        return redirect()->route('tenant.admin.administration.roles.index')->with('status', 'Role created.');
    }

    public function edit(TenantRole $role): Response
    {
        Gate::authorize('update', $role);

        return Inertia::render('Tenant/Admin/Administration/Roles/Edit', [
            'role' => $role->load('permissions')->loadCount('users'),
            'permissionGroups' => $this->groupedPermissions(),
        ]);
    }

    public function update(Request $request, TenantRole $role): RedirectResponse
    {
        Gate::authorize('update', $role);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $oldPermissions = $role->permissions->pluck('name')->all();
        $role->update(['label' => $data['label']]);

        $newPermissions = $this->resolveWithDependencies($data['permissions'] ?? []);
        $role->syncPermissions($newPermissions);

        $this->auditLog->record(
            event: 'role.permissions_changed',
            actor: $request->user('tenant'),
            description: "Updated permissions for role \"{$role->name}\"",
            subject: $role,
            oldValues: ['permissions' => $oldPermissions],
            newValues: ['permissions' => $newPermissions->pluck('name')->all()],
        );

        return redirect()->route('tenant.admin.administration.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(TenantRole $role): RedirectResponse
    {
        Gate::authorize('delete', $role);

        if ($role->users()->exists()) {
            return back()->with('error', 'Reassign users away from this role before deleting it.');
        }

        $name = $role->name;
        $role->delete();

        $this->auditLog->record('role.deleted', request()->user('tenant'), "Deleted role \"{$name}\"", subjectType: TenantRole::class, subjectLabel: $name);

        return back()->with('status', 'Role deleted.');
    }

    /** @return array<string, mixed> */
    private function groupedPermissions(): array
    {
        return TenantPermission::query()->orderBy('group')->orderBy('name')->get()
            ->groupBy(fn (TenantPermission $permission) => $permission->group ?? 'General')
            ->map(fn ($permissions) => $permissions->values())
            ->all();
    }

    /** Server-side enforcement of permission dependencies — never trust the frontend matrix alone. */
    private function resolveWithDependencies(array $permissionIds): Collection
    {
        $dependencies = [
            'users.update' => 'users.view',
            'users.suspend' => 'users.view',
            'users.delete' => 'users.view',
            'users.manage_roles' => 'users.view',
            'users.manage_sessions' => 'users.view',
            'invitations.create' => 'invitations.view',
            'invitations.resend' => 'invitations.view',
            'invitations.revoke' => 'invitations.view',
            'teams.update' => 'teams.view',
            'teams.delete' => 'teams.view',
            'teams.manage_members' => 'teams.view',
            'departments.update' => 'departments.view',
            'departments.delete' => 'departments.view',
            'roles.update' => 'roles.view',
            'roles.delete' => 'roles.view',
            'roles.assign' => 'roles.view',
            'permissions.assign' => 'permissions.view',
            'audit_logs.export' => 'audit_logs.view',
            'security.update' => 'security.view',
            'security.revoke_sessions' => 'security.view_sessions',
        ];

        $permissions = TenantPermission::query()->whereIn('id', $permissionIds)->get();
        $names = $permissions->pluck('name')->all();

        foreach ($dependencies as $needs => $requires) {
            if (in_array($needs, $names, true) && ! in_array($requires, $names, true)) {
                $names[] = $requires;
            }
        }

        return TenantPermission::query()->whereIn('name', array_unique($names))->get();
    }
}
