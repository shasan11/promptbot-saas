<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantStoreRequest;
use App\Http\Requests\Admin\TenantUpdateRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->with(['plan', 'domains'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($inner) => $inner->where('company_name', 'like', $search)->orWhere('slug', 'like', $search));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tenants/Create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'provisioningMode' => config('saas.db_provisioning_mode'),
        ]);
    }

    public function store(TenantStoreRequest $request): RedirectResponse
    {
        $tenant = $this->provisioning->provision($request->validated());
        $this->auditLog->record('tenant.created', $tenant, [
            'tenant_id' => $tenant->id,
            'new_values' => $request->safe()->except(['owner_password', 'database_password']),
        ]);

        return redirect()->route('superadmin.tenants.show', $tenant)->with('status', 'Tenant provisioning completed.');
    }

    public function show(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant->load(['plan.features', 'domains', 'subscriptions.plan', 'provisioningLogs' => fn ($query) => $query->latest()->limit(50)]),
        ]);
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Create', [
            'tenant' => $tenant,
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'provisioningMode' => config('saas.db_provisioning_mode'),
        ]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $oldValues = $tenant->only(array_keys($request->validated()));
        $tenant->update($request->validated());
        $this->auditLog->record('tenant.updated', $tenant, [
            'tenant_id' => $tenant->id,
            'old_values' => $oldValues,
            'new_values' => $request->validated(),
        ]);

        return redirect()->route('superadmin.tenants.show', $tenant)->with('status', 'Tenant updated.');
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->provisioning->delete($tenant);
        $this->auditLog->record('tenant.deleted', $tenant, [
            'tenant_id' => $tenant->id,
            'severity' => 'warning',
        ]);

        return redirect()->route('superadmin.tenants.index')->with('status', 'Tenant deleted.');
    }

    public function retry(Tenant $tenant): RedirectResponse
    {
        $this->provisioning->retry($tenant);
        $this->auditLog->record('tenant.provisioning_retried', $tenant, ['tenant_id' => $tenant->id]);

        return back()->with('status', 'Tenant provisioning retried.');
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $this->provisioning->suspend($tenant);
        $this->auditLog->record('tenant.suspended', $tenant, [
            'tenant_id' => $tenant->id,
            'reason' => request('reason'),
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Tenant suspended.');
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $this->provisioning->activate($tenant);
        $this->auditLog->record('tenant.activated', $tenant, [
            'tenant_id' => $tenant->id,
            'reason' => request('reason'),
        ]);

        return back()->with('status', 'Tenant activated.');
    }

    public function migrate(Tenant $tenant): RedirectResponse
    {
        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        $this->auditLog->record('tenant.migrated', $tenant, ['tenant_id' => $tenant->id]);

        return back()->with('status', 'Tenant migrations ran.');
    }

    public function seed(Tenant $tenant): RedirectResponse
    {
        Artisan::call('tenants:seed', ['--tenants' => [$tenant->id], '--class' => 'Database\\Seeders\\TenantDatabaseSeeder', '--force' => true]);
        $this->auditLog->record('tenant.seeded', $tenant, ['tenant_id' => $tenant->id]);

        return back()->with('status', 'Tenant seeders ran.');
    }
}
