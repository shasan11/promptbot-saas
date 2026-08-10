<?php

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TenantPermission;
use App\Models\TenantRole;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $aiVisible = auth('tenant')->user()?->can('ai.view') && app(\App\Services\SaaS\TenantFeatureService::class)->enabled('ai_platform') && Schema::hasTable('ai_runs');
        return Inertia::render('Tenant/Admin/Dashboard', [
            'tenant' => [
                'id' => tenant('id'),
                'company_name' => tenant('company_name'),
            ],
            'stats' => [
                'users' => User::query()->count(),
                'roles' => TenantRole::query()->count(),
                'permissions' => TenantPermission::query()->count(),
                'settings' => Setting::query()->count(),
            ],
            'recentUsers' => User::query()
                ->with('roles:id,name,label')
                ->latest()
                ->limit(6)
                ->get(['id', 'name', 'email', 'created_at']),
            'ai' => $aiVisible ? [
                'active_agents' => \App\Models\AI\Agent::query()->where('status', 'active')->count(),
                'runs_today' => \App\Models\AI\Run::query()->where('created_at', '>=', now()->startOfDay())->count(),
                'pending_approvals' => \App\Models\AI\ApprovalRequest::query()->where('status', 'pending')->count(),
                'failed_today' => \App\Models\AI\Run::query()->where('created_at', '>=', now()->startOfDay())->where('status', 'failed')->count(),
            ] : null,
        ]);
    }
}
