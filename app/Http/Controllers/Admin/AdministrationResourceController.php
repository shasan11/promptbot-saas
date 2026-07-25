<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformAdminLoginAttempt;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationResourceController extends Controller
{
    public function roles(): Response
    {
        return Inertia::render('Admin/Administration/Roles/Index', [
            'roles' => PlatformRole::query()->withCount('permissions')->orderBy('name')->paginate(20),
        ]);
    }

    public function permissions(): Response
    {
        return Inertia::render('Admin/Administration/Permissions/Index', [
            'permissions' => PlatformPermission::query()->orderBy('name')->paginate(50),
        ]);
    }

    public function auditLogs(): Response
    {
        return Inertia::render('Admin/Administration/AuditLogs/Index', [
            'logs' => AuditLog::query()->with('administrator:id,name,email')->latest()->paginate(50),
        ]);
    }

    public function loginAttempts(): Response
    {
        return Inertia::render('Admin/Administration/LoginAttempts/Index', [
            'attempts' => PlatformAdminLoginAttempt::query()->with('administrator:id,name,email')->latest('attempted_at')->paginate(50),
        ]);
    }

    public function sessions(): Response
    {
        return Inertia::render('Admin/Administration/Sessions/Index', [
            'sessions' => DB::table('platform_admin_sessions')->latest()->paginate(50),
        ]);
    }
}
