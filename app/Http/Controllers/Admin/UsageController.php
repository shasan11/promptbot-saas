<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerAccount;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\TenantUsageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function __invoke(Request $request, TenantUsageService $usage): Response
    {
        $tenants = Tenant::query()->with(['customerAccount:id,name', 'plan:id,name'])
            ->when($request->filled('account_id'), fn ($query) => $query->where('customer_account_id', $request->integer('account_id')))
            ->when($request->filled('plan_id'), fn ($query) => $query->where('plan_id', $request->integer('plan_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('company_name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('company_name')->paginate(25)->withQueryString();
        $selected = $request->filled('tenant_id') ? Tenant::with(['customerAccount', 'plan'])->findOrFail($request->string('tenant_id')) : null;

        return Inertia::render('Admin/Usage/Index', [
            'tenants' => $tenants, 'selectedTenant' => $selected,
            'usage' => $selected ? $usage->snapshot($selected, $request->string('period', 'current_month')->toString(), $request->string('feature')->toString() ?: null) : null,
            'accounts' => CustomerAccount::orderBy('name')->get(['id', 'name']),
            'plans' => Plan::orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['account_id', 'plan_id', 'tenant_id', 'search', 'feature', 'period']),
        ]);
    }
}
