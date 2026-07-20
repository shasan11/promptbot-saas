<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Tenant/Admin/Dashboard', [
            'tenant' => [
                'id' => tenant('id'),
                'company_name' => tenant('company_name'),
            ],
            'stats' => [
                'users' => User::query()->count(),
                'roles' => Role::query()->count(),
                'permissions' => Permission::query()->count(),
                'settings' => Setting::query()->count(),
            ],
            'recentUsers' => User::query()
                ->with('roles:id,name,label')
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'email', 'created_at']),
        ]);
    }
}
