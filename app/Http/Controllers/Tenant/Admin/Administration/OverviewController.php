<?php

namespace App\Http\Controllers\Tenant\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Team;
use App\Models\TenantActivityLog;
use App\Models\TenantInvitation;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user('tenant');

        $checks = collect([
            [
                'severity' => 'warning',
                'title' => 'Users without a role',
                'description' => 'Some users have no role assigned and cannot access role-gated features.',
                'count' => User::query()->doesntHave('roles')->count(),
                'route' => 'tenant.admin.administration.users.index',
            ],
            [
                'severity' => 'info',
                'title' => 'Roles with no users',
                'description' => 'Custom roles exist that nobody currently holds.',
                'count' => TenantRole::query()->doesntHave('users')->count(),
                'route' => 'tenant.admin.administration.roles.index',
            ],
            [
                'severity' => 'warning',
                'title' => 'Invitations pending over 7 days',
                'description' => 'These invitations are approaching or past their expiration.',
                'count' => TenantInvitation::query()->where('status', 'pending')->where('created_at', '<', now()->subDays(7))->count(),
                'route' => 'tenant.admin.administration.invitations.index',
            ],
            [
                'severity' => 'info',
                'title' => 'Departments with no members',
                'description' => 'Empty departments may just need users assigned.',
                'count' => Department::query()->where('status', 'active')->doesntHave('users')->count(),
                'route' => 'tenant.admin.administration.departments.index',
            ],
        ])->filter(fn (array $check) => $check['count'] > 0)->values();

        return Inertia::render('Tenant/Admin/Administration/Overview', [
            'stats' => [
                'activeUsers' => User::query()->where('status', 'active')->count(),
                'pendingInvitations' => TenantInvitation::query()->where('status', 'pending')->count(),
                'suspendedUsers' => User::query()->where('status', 'suspended')->count(),
                'teams' => Team::query()->where('status', 'active')->count(),
                'departments' => Department::query()->where('status', 'active')->count(),
                'customRoles' => TenantRole::query()->count(),
                'usersWithoutRoles' => User::query()->doesntHave('roles')->count(),
            ],
            'checks' => $checks,
            'recentActivity' => TenantActivityLog::query()
                ->with('actor:id,name')
                ->latest('created_at')
                ->limit(10)
                ->get(),
        ]);
    }
}
