<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TenantStoreRequest;
use App\Http\Requests\Admin\TenantUpdateRequest;
use App\Models\CustomerAccount;
use App\Models\CustomerAccountActivity;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\WorkspacePurchaseRequest;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\DefaultCustomerAccountService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PortalNotificationService;
use App\Services\Platform\SubscriptionService;
use App\Services\Platform\TenantUsageService;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogService $auditLog,
        private readonly TenantUsageService $usage,
        private readonly PortalNotificationService $portalNotifications,
        private readonly PlatformSettingsService $settings,
        private readonly DefaultCustomerAccountService $defaultAccount,
    ) {}

    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->with(['plan', 'domains', 'customerAccount.owner', 'subscriptions' => fn ($query) => $query->with('plan')->latest()])
            ->withCount(['subscriptions', 'invoices', 'payments', 'supportTickets'])
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request): void {
                $status = $request->string('status')->toString();
                if (in_array($status, ['trial', 'past_due', 'cancelled'], true)) {
                    $query->whereHas('subscriptions', fn ($subscription) => $subscription->where('status', $status));
                } elseif ($status === 'failed') {
                    $query->where(fn ($inner) => $inner->where('status', 'failed')->orWhereNotNull('last_provisioning_error'));
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('company_name', 'like', $search)
                        ->orWhere('slug', 'like', $search)
                        ->orWhereHas('customerAccount', fn ($account) => $account->where('name', 'like', $search)->orWhere('account_number', 'like', $search))
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

    public function create(Request $request): Response
    {
        $defaultAccount = $this->defaultAccount->get();

        return Inertia::render('Admin/Tenants/Create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'accounts' => CustomerAccount::query()->whereNotIn('status', ['closed'])->orderBy('name')->limit(500)->get(['id', 'public_uuid', 'name', 'account_number']),
            'provisioningMode' => (string) $this->settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode')),
            'tenantBaseDomain' => config('saas.tenant_base_domain'),
            'defaultRegion' => (string) $this->settings->get('tenant_provisioning', 'default_region', ''),
            'selectedAccountId' => $request->integer('account_id') ?: null,
            'defaultAccountId' => $defaultAccount->getKey(),
            'queue' => $this->queueConfiguration(),
        ]);
    }

    public function store(TenantStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $executionMode = $data['execution_mode'] ?? 'immediate';
        unset($data['execution_mode']);

        if ($executionMode === 'queue') {
            if (! $this->queueConfiguration()['available']) {
                throw ValidationException::withMessages([
                    'execution_mode' => 'Laravel Queue is not available. Enable a non-sync queue connection and start a worker, or choose immediate provisioning.',
                ]);
            }

            $tenant = $this->provisioning->queueProvisioning($data);
        } else {
            $tenant = $this->provisioning->provision($data);
        }

        $this->auditLog->record('tenant.created', $tenant, [
            'tenant_id' => $tenant->id,
            'new_values' => [
                ...$request->safe()->except(['owner_password', 'database_password']),
                'execution_mode' => $executionMode,
            ],
        ]);

        return redirect()->route('superadmin.tenants.show', $tenant)->with(
            'status',
            $executionMode === 'queue'
                ? 'Tenant provisioning was queued. Progress is available on the Provisioning tab.'
                : 'Tenant provisioning completed.'
        );
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'plan.features',
            'customerAccount.owner',
            'domains',
            'subscriptions.plan',
            'invoices' => fn ($query) => $query->latest('issued_on')->limit(20),
            'payments' => fn ($query) => $query->latest()->limit(20),
            'supportTickets' => fn ($query) => $query->latest('last_activity_at')->limit(20),
            'provisioningLogs' => fn ($query) => $query->latest()->limit(50),
        ])->loadCount(['invoices', 'payments', 'supportTickets']);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
            'usage' => $this->usage->snapshot($tenant),
            'activities' => $tenant->customerAccount?->activities()->where('tenant_id', $tenant->id)->limit(30)->get() ?? [],
        ]);
    }

    public function edit(Tenant $tenant): Response
    {
        $defaultAccount = $this->defaultAccount->get();

        return Inertia::render('Admin/Tenants/Create', [
            'tenant' => $tenant->load('domains'),
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'accounts' => CustomerAccount::query()->whereNotIn('status', ['closed'])->orderBy('name')->limit(500)->get(['id', 'public_uuid', 'name', 'account_number']),
            'provisioningMode' => (string) $this->settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode')),
            'tenantBaseDomain' => config('saas.tenant_base_domain'),
            'defaultRegion' => (string) $this->settings->get('tenant_provisioning', 'default_region', ''),
            'defaultAccountId' => $defaultAccount->getKey(),
        ]);
    }

    public function update(TenantUpdateRequest $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validated();
        if (array_key_exists('customer_account_id', $validated) && ! $validated['customer_account_id']) {
            $validated['customer_account_id'] = $this->defaultAccount->get()->getKey();
        }
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
            $oldAccountId = $tenant->customer_account_id;
            if ($validated !== []) {
                $tenant->update($validated);
            }

            if (array_key_exists('customer_account_id', $validated) && (int) $oldAccountId !== (int) $validated['customer_account_id']) {
                foreach (['subscriptions', 'invoices', 'payments', 'support_tickets'] as $table) {
                    DB::table($table)->where('tenant_id', $tenant->getKey())->update([
                        'customer_account_id' => $validated['customer_account_id'],
                        'updated_at' => now(),
                    ]);
                }
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

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $this->provisioning->delete($tenant);
        $this->auditLog->record('tenant.deleted', $tenant, [
            'tenant_id' => $tenant->id,
            'severity' => 'warning',
            'reason' => $reason,
        ]);

        return redirect()->route('superadmin.tenants.index')->with('status', 'Tenant deleted.');
    }

    public function retry(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $purchase = WorkspacePurchaseRequest::with('portalUser')->where('tenant_id', $tenant->getKey())
            ->whereIn('status', ['failed', 'provisioning'])->latest()->first();
        $snapshot = $purchase?->request_snapshot ?? [];
        $createOwner = (bool) ($snapshot['create_tenant_owner'] ?? false);
        $tenant = $this->provisioning->retry($tenant, [
            'provisioning_mode' => (string) $this->settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode', 'manual')),
            'billing_interval' => $snapshot['billing_interval'] ?? 'monthly',
            'create_tenant_owner' => $createOwner,
            'owner_name' => $createOwner ? $purchase?->portalUser?->name : null,
            'owner_email' => $createOwner ? $purchase?->portalUser?->email : null,
            'owner_password' => $createOwner ? Str::password(32) : null,
        ]);
        $completedPurchases = WorkspacePurchaseRequest::query()->where('tenant_id', $tenant->getKey())
            ->whereIn('status', ['failed', 'provisioning'])->update([
                'status' => 'completed', 'failure_reason' => null, 'updated_at' => now(),
            ]);
        if ($completedPurchases && $tenant->customer_account_id) {
            CustomerAccountActivity::create([
                'customer_account_id' => $tenant->customer_account_id, 'tenant_id' => $tenant->getKey(),
                'actor_type' => $request->user('central')::class, 'actor_id' => (string) $request->user('central')->getKey(),
                'event' => 'workspace.provisioning_recovered', 'subject_type' => Tenant::class,
                'subject_id' => $tenant->getKey(), 'description' => "Provisioning for {$tenant->company_name} recovered successfully.",
            ]);
            $this->portalNotifications->capability($tenant->customer_account_id, 'can_manage_services', 'workspace.ready', 'Workspace ready', "{$tenant->company_name} is ready to use.", route('portal.workspaces.show', $tenant, false), ['tenant_id' => $tenant->getKey()], $tenant->getKey());
        }
        $this->auditLog->record('tenant.provisioning_retried', $tenant, ['tenant_id' => $tenant->id, 'reason' => $reason]);

        return back()->with('status', 'Tenant provisioning retried.');
    }

    public function suspend(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $this->provisioning->suspend($tenant);
        $this->auditLog->record('tenant.suspended', $tenant, [
            'tenant_id' => $tenant->id,
            'reason' => $reason,
            'severity' => 'warning',
        ]);
        if ($tenant->customer_account_id) {
            CustomerAccountActivity::create([
                'customer_account_id' => $tenant->customer_account_id, 'tenant_id' => $tenant->getKey(),
                'actor_type' => $request->user('central')::class, 'actor_id' => (string) $request->user('central')->getKey(),
                'event' => 'workspace.suspended', 'subject_type' => Tenant::class, 'subject_id' => $tenant->getKey(),
                'description' => "{$tenant->company_name} was suspended.", 'metadata' => ['reason' => $reason],
            ]);
            $this->portalNotifications->capability($tenant->customer_account_id, 'can_manage_services', 'workspace.suspended', 'Workspace suspended', "{$tenant->company_name} was suspended. Contact support if you need assistance.", route('portal.workspaces.show', $tenant, false), ['tenant_id' => $tenant->getKey()], $tenant->getKey());
        }

        return back()->with('status', 'Tenant suspended.');
    }

    public function activate(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        $this->provisioning->activate($tenant);
        $this->auditLog->record('tenant.activated', $tenant, [
            'tenant_id' => $tenant->id,
            'reason' => $reason,
        ]);

        return back()->with('status', 'Tenant activated.');
    }

    public function migrate(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        $this->auditLog->record('tenant.migrated', $tenant, ['tenant_id' => $tenant->id, 'reason' => $reason]);

        return back()->with('status', 'Tenant migrations ran.');
    }

    public function seed(Request $request, Tenant $tenant): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:1000']])['reason'];
        Artisan::call('tenants:seed', ['--tenants' => [$tenant->id], '--class' => 'Database\\Seeders\\TenantDatabaseSeeder', '--force' => true]);
        $this->auditLog->record('tenant.seeded', $tenant, ['tenant_id' => $tenant->id, 'reason' => $reason]);

        return back()->with('status', 'Tenant seeders ran.');
    }

    private function queueConfiguration(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connection}.driver", $connection);
        $hasStorage = $driver !== 'database' || Schema::hasTable('jobs');
        $available = ! in_array($driver, ['sync', 'null'], true) && $hasStorage;

        return [
            'available' => $available,
            'connection' => $connection,
            'driver' => $driver,
            'workerCommand' => "php artisan queue:work {$connection} --queue=provisioning,default --tries=1 --timeout=1800",
            'enableEnvironment' => 'QUEUE_CONNECTION=database',
            'reason' => $available
                ? null
                : ($hasStorage
                    ? 'The application is using the synchronous queue driver.'
                    : 'The database queue tables have not been migrated.'),
        ];
    }
}
