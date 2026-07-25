<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\DashboardMetricsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardMetricsService $metrics): Response
    {
        return Inertia::render('Admin/Dashboard', $metrics->get($request->only(['date_range', 'plan', 'currency', 'country', 'tenant_status'])));
    }
}
