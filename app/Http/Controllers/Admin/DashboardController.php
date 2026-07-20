<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'activeTenants' => Tenant::query()->where('status', TenantStatus::Active)->count(),
                'plans' => Plan::query()->count(),
                'subscriptions' => Subscription::query()->count(),
                'features' => Feature::query()->count(),
            ],
            'recentTenants' => Tenant::query()
                ->with(['plan', 'domains'])
                ->latest()
                ->limit(6)
                ->get(),
            'subscriptionsByStatus' => Subscription::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
        ]);
    }
}
