<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantStoreRequest;
use App\Http\Requests\Admin\TenantUpdateRequest;
use App\Jobs\ProvisionTenant;
use App\Jobs\RunTenantMigrations;
use App\Jobs\RunTenantSeeders;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\OperationService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly AuditLogService $auditLog,
        private readonly OperationService $operations,
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
        $operation = $this->operations->create('tenant.provision', null, [
            'reason' => 'Tenant created from superadmin wizard.',
            'new_values' => $request->safe()->except(['owner_password', 'database_password']),
        ]);
        ProvisionTenant::dispatch($operation->id, $request->validated());

        $this->auditLog->record('tenant.provisioning_queued', $operation, [
            'new_values' => $request->safe()->except(['owner_password', 'database_password']),
        ]);

        return redirect()->route('superadmin.operations.show', $operation)->with('status', 'Tenant provisioning queued.');
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
        $operation = $this->operations->create('tenant.provision.retry', $tenant, ['reason' => request('reason')]);
        ProvisionTenant::dispatch($operation->id, [
            'company_name' => $tenant->company_name,
            'slug' => $tenant->slug,
            'owner_name' => 'Tenant Owner',
            'owner_email' => 'owner@'.$tenant->slug.'.invalid',
            'owner_password' => str()->password(24),
            'provisioning_mode' => config('saas.db_provisioning_mode', 'manual'),
        ]);
        $this->auditLog->record('tenant.provisioning_retried', $tenant, ['tenant_id' => $tenant->id, 'reason' => request('reason')]);

        return redirect()->route('superadmin.operations.show', $operation)->with('status', 'Tenant provisioning retry queued.');
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
        $operation = $this->operations->create('tenant.migrate', $tenant, ['reason' => request('reason')]);
        RunTenantMigrations::dispatch($operation->id, $tenant->id);
        $this->auditLog->record('tenant.migration_queued', $tenant, ['tenant_id' => $tenant->id, 'reason' => request('reason')]);

        return redirect()->route('superadmin.operations.show', $operation)->with('status', 'Tenant migrations queued.');
    }

    public function seed(Tenant $tenant): RedirectResponse
    {
        $operation = $this->operations->create('tenant.seed', $tenant, ['reason' => request('reason')]);
        RunTenantSeeders::dispatch($operation->id, $tenant->id);
        $this->auditLog->record('tenant.seed_queued', $tenant, ['tenant_id' => $tenant->id, 'reason' => request('reason')]);

        return redirect()->route('superadmin.operations.show', $operation)->with('status', 'Tenant seeders queued.');
    }
}
