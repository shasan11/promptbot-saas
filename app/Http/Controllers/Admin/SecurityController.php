<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CentralUser;
use App\Models\PlatformAdminLoginAttempt;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function admins(Request $request): Response
    {
        return $this->render('admins', [
            'admins' => CentralUser::with('roles')->latest()->paginate(20),
            'roles' => PlatformRole::orderBy('name')->get(),
        ]);
    }

    public function roles(): Response
    {
        return $this->render('roles', [
            'roles' => PlatformRole::with('permissions')->orderBy('name')->get(),
            'permissions' => PlatformPermission::orderBy('name')->get(),
        ]);
    }

    public function audit(Request $request): Response
    {
        return $this->render('audit', ['auditLogs' => AuditLog::with('administrator:id,name,email')->latest()->paginate(30)]);
    }

    public function logins(): Response
    {
        return $this->render('logins', ['loginAttempts' => PlatformAdminLoginAttempt::with('administrator:id,name,email')->latest()->paginate(30)]);
    }

    public function storeAdmin(Request $request, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'unique:central_users,email'], 'role_id' => ['required', 'exists:platform_roles,id']]);
        $admin = CentralUser::create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make(Str::password(40)), 'role' => 'administrator', 'is_active' => true]);
        $admin->assignRole(PlatformRole::findOrFail($data['role_id']));
        Password::broker('central_users')->sendResetLink(['email' => $admin->email]);
        $audit->record('platform_admin.invited', $admin, ['new_values' => ['email' => $admin->email, 'role_id' => $data['role_id']]]);
        return back()->with('status', 'Administrator invited and password setup email sent.');
    }

    public function status(Request $request, CentralUser $admin, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['is_active' => ['required', 'boolean'], 'reason' => ['required', 'string', 'max:1000']]);
        if (! $data['is_active'] && $admin->hasRole('Platform Owner')) {
            abort_if(CentralUser::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', 'Platform Owner'))->count() <= 1, 422, 'The last Platform Owner cannot be suspended.');
        }
        abort_if($admin->is($request->user('central')) && ! $data['is_active'], 422, 'You cannot suspend your own session.');
        $admin->update(['is_active' => $data['is_active']]);
        $audit->record('platform_admin.status_changed', $admin, ['new_values' => ['is_active' => $data['is_active']], 'reason' => $data['reason']]);
        return back()->with('status', 'Administrator status updated.');
    }

    public function access(Request $request, CentralUser $admin, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:reset_access,require_2fa,revoke_sessions,assign_role'],
            'required' => ['nullable', 'boolean'],
            'role_id' => ['nullable', 'required_if:action,assign_role', 'exists:platform_roles,id'],
        ]);

        if ($data['action'] === 'reset_access') {
            Password::broker('central_users')->sendResetLink(['email' => $admin->email]);
        } elseif ($data['action'] === 'require_2fa') {
            $admin->update(['two_factor_required' => (bool) ($data['required'] ?? true)]);
        } elseif ($data['action'] === 'revoke_sessions') {
            DB::table('sessions')->where('user_id', $admin->id)->delete();
        } else {
            $role = PlatformRole::findOrFail($data['role_id']);
            if ($admin->hasRole('Platform Owner') && $role->name !== 'Platform Owner') {
                abort_if(CentralUser::where('is_active', true)->whereHas('roles', fn ($query) => $query->where('name', 'Platform Owner'))->count() <= 1, 422, 'The last Platform Owner cannot be reassigned.');
            }
            $admin->syncRoles([$role]);
        }

        $audit->record('platform_admin.'.$data['action'], $admin, ['new_values' => collect($data)->except('action')->all()]);
        return back()->with('status', match ($data['action']) {
            'reset_access' => 'Password setup email sent.', 'require_2fa' => 'Two-factor requirement updated.',
            'revoke_sessions' => 'Administrator sessions revoked.', default => 'Administrator role updated.',
        });
    }

    public function updateRole(Request $request, PlatformRole $role, AuditLogService $audit): RedirectResponse
    {
        $data = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', 'exists:platform_permissions,name']]);
        $role->syncPermissions($data['permissions']);
        $audit->record('platform_role.permissions_updated', $role, ['new_values' => ['permissions' => $data['permissions']]]);
        return back()->with('status', 'Role permissions updated.');
    }

    private function render(string $tab, array $data): Response
    {
        return Inertia::render('Admin/Security/Index', ['tab' => $tab, ...$data]);
    }
}
