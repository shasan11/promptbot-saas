<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantStoreRequest;
use App\Http\Requests\Admin\TenantUpdateRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\SubscriptionService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogService $auditLog,
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->with(['plan', 'domains'])
            ->withCount(['subscriptions', 'invoices', 'payments', 'supportTickets'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('company_name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('domains', fn ($domains) => $domains->where('domain', 'like', $search));
                });
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
            'tenantBaseDomain' => config('saas.tenant_base_domain'),
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
        $tenant->load([
            'plan.features',
            'domains',
            'subscriptions.plan',
            'provisioningLogs' => fn ($query) => $query->latest()->limit(50),
        ])->loadCount(['invoices', 'payments', 'supportTickets']);

        return Inertia::render('Admin/Tenants/Show', ['tenant' => $tenant]);
    }

    public function edit(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Create', [
            'tenant' => $tenant->load('domains'),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'provisioningMode' => config('saas.db_provisioning_mode'),
            'tenantBaseDomain' => config('saas.tenant_base_domain'),
        ]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();
        $subdomain = $validated['subdomain'] ?? null;
        $planId = array_key_exists('plan_id', $validated) ? $validated['plan_id'] : null;
        unset($validated['subdomain'], $validated['plan_id']);

        $primaryDomain = $tenant->domains()->where('is_primary', true)->first() ?? $tenant->domains()->first();
        $oldValues = [
            ...$tenant->only(array_keys($validated)),
            'plan_id' => $tenant->plan_id,
            'subdomain' => $primaryDomain?->domain,
        ];

        DB::transaction(function () use ($tenant, $validated, $subdomain, $planId, $primaryDomain): void {
            if ($validated !== []) {
                $tenant->update($validated);
            }

            if ($subdomain !== null) {
                if ($primaryDomain) {
                    $primaryDomain->update([
                        'domain' => $subdomain,
                        'type' => 'subdomain',
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'is_primary' => true,
                    ]);
                } else {
                    $tenant->domains()->create([
                        'domain' => $subdomain,
                        'type' => 'subdomain',
                        'verification_status' => 'verified',
                        'verified_at' => now(),
                        'is_primary' => true,
                    ]);
                }
            }

            if ($planId !== null && (int) $planId !== (int) $tenant->plan_id) {
                $subscription = $tenant->subscriptions()->latest()->first();

                if ($subscription) {
                    $subscription->update(['plan_id' => $planId]);
                    $this->subscriptions->syncTenantPlan($subscription->fresh());
                } else {
                    $tenant->forceFill(['plan_id' => $planId])->save();
                }
            }
        });

        $newValues = [
            ...$validated,
            'plan_id' => $planId ?? $tenant->fresh()->plan_id,
            'subdomain' => $subdomain ?? $primaryDomain?->domain,
        ];

        $this->auditLog->record('tenant.updated', $tenant, [
            'tenant_id' => $tenant->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        return redirect()->route('superadmin.tenants.show', $tenant)->with('status', 'Tenant and subdomain updated.');
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
