<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\CustomerAccountActivity;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Tenant;
use App\Models\WorkspacePurchaseRequest;
use App\Services\Tenancy\TenantProvisioningService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class WorkspacePurchaseService
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly PortalNotificationService $notifications,
        private readonly PlatformSettingsService $settings,
        private readonly InvoiceService $invoices,
    ) {}

    public function purchase(CustomerAccount $account, PortalUser $user, array $data, string $idempotencyKey): Tenant|WorkspacePurchaseRequest
    {
        $scopeKey = hash('sha256', $account->getKey().'|'.$idempotencyKey);
        return Cache::lock('workspace-purchase:'.$scopeKey, 900)->block(5, function () use ($account, $user, $data, $scopeKey) {
            $request = WorkspacePurchaseRequest::where('idempotency_key', $scopeKey)->first();
            if ($request?->status === 'completed' && $request->tenant_id) return Tenant::findOrFail($request->tenant_id);
            if ($request?->invoice_id && $request->invoice?->status !== 'paid') return $request;

            $request ??= DB::transaction(fn () => WorkspacePurchaseRequest::create([
                'customer_account_id' => $account->getKey(), 'portal_user_id' => $user->getKey(),
                'idempotency_key' => $scopeKey, 'status' => 'pending',
                'request_snapshot' => collect($data)->except(['owner_password'])->all(),
            ]));

            if ($this->requiresPaymentForPlan(Plan::findOrFail($data['plan_id'])) && ! $request->invoice_id) {
                $invoice = $this->createPurchaseInvoice($account, $data);
                $request->update(['invoice_id' => $invoice->getKey(), 'status' => 'awaiting_payment']);
                CustomerAccountActivity::create([
                    'customer_account_id' => $account->getKey(), 'actor_type' => PortalUser::class,
                    'actor_id' => (string) $user->getKey(), 'event' => 'workspace.payment_required',
                    'subject_type' => WorkspacePurchaseRequest::class, 'subject_id' => (string) $request->getKey(),
                    'description' => "Payment is required before {$data['workspace_name']} can be provisioned.",
                ]);
                return $request->fresh('invoice');
            }

            return $this->fulfill($request, $account, $user, $data);
        });
    }

    public function fulfillForInvoice(Invoice $invoice): ?Tenant
    {
        if ($invoice->status !== 'paid') return null;
        $request = WorkspacePurchaseRequest::with(['account', 'portalUser'])->where('invoice_id', $invoice->getKey())->first();
        if (! $request) return null;
        if ($request->status === 'completed' && $request->tenant_id) return Tenant::find($request->tenant_id);

        return Cache::lock('workspace-purchase-fulfill:'.$request->getKey(), 900)->block(5, function () use ($request) {
            $request->refresh();
            if ($request->status === 'completed' && $request->tenant_id) return Tenant::find($request->tenant_id);
            return $this->fulfill($request, $request->account, $request->portalUser, $request->request_snapshot);
        });
    }

    public function retryFailed(Tenant $tenant, PortalUser $user): Tenant
    {
        $request = WorkspacePurchaseRequest::with(['account', 'portalUser'])
            ->where('tenant_id', $tenant->getKey())->where('status', 'failed')->latest()->firstOrFail();
        abort_unless((int) $request->customer_account_id === (int) $tenant->customer_account_id
            && $user->belongsToAccount($request->customer_account_id), 404);

        return Cache::lock('workspace-purchase-fulfill:'.$request->getKey(), 900)->block(5, function () use ($request) {
            $request->refresh();
            if ($request->status === 'completed' && $request->tenant_id) return Tenant::findOrFail($request->tenant_id);
            abort_unless($request->status === 'failed', 422, 'This workspace is not waiting for a provisioning retry.');

            return $this->fulfill($request, $request->account, $request->portalUser, $request->request_snapshot);
        });
    }

    private function fulfill(WorkspacePurchaseRequest $request, CustomerAccount $account, PortalUser $user, array $data): Tenant
    {
        $request->update(['status' => 'provisioning', 'failure_reason' => null]);
        try {
            $createTenantOwner = (bool) ($data['create_tenant_owner'] ?? false);
            $provisioningData = [
                ...$data, 'customer_account_id' => $account->getKey(), 'company_name' => $data['workspace_name'],
                'slug' => $data['slug'], 'subdomain' => $data['slug'].'.'.config('saas.tenant_base_domain'),
                'create_tenant_owner' => $createTenantOwner, 'owner_name' => $createTenantOwner ? $user->name : null,
                'owner_email' => $createTenantOwner ? $user->email : null, 'owner_password' => $createTenantOwner ? Str::password(32) : null,
            ];
            $retries = max(0, (int) $this->settings->get('tenant_provisioning', 'automatic_retry_count', 0));
            for ($attempt = 0; ; $attempt++) {
                try {
                    $tenant = $this->provisioning->provision($provisioningData);
                    break;
                } catch (Throwable $exception) {
                    if ($attempt >= $retries) throw $exception;
                }
            }
            DB::transaction(function () use ($request, $tenant, $account, $user): void {
                $request->update(['status' => 'completed', 'tenant_id' => $tenant->getKey(), 'failure_reason' => null]);
                $membership = $account->users()->where('portal_users.id', $user->getKey())->first()?->pivot;
                if ($membership && $membership->role !== 'owner' && $membership->service_access === 'selected') {
                    DB::table('customer_account_user_tenants')->insertOrIgnore([
                        'customer_account_id' => $account->getKey(), 'portal_user_id' => $user->getKey(), 'tenant_id' => $tenant->getKey(),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                CustomerAccountActivity::create([
                    'customer_account_id' => $account->getKey(), 'tenant_id' => $tenant->getKey(),
                    'actor_type' => PortalUser::class, 'actor_id' => (string) $user->getKey(), 'event' => 'workspace.created',
                    'subject_type' => Tenant::class, 'subject_id' => $tenant->getKey(), 'description' => "Workspace {$tenant->company_name} created.",
                ]);
            });
            $this->notifications->capability($account, 'can_manage_services', 'workspace.ready', 'Workspace ready', "{$tenant->company_name} is ready to use.", route('portal.workspaces.show', $tenant, false), ['tenant_id' => $tenant->getKey()], $tenant->getKey());
            return $tenant;
        } catch (Throwable $exception) {
            $failedTenant = Tenant::query()->where('slug', $data['slug'])
                ->where('customer_account_id', $account->getKey())->first();
            $request->update([
                'status' => 'failed',
                'tenant_id' => $failedTenant?->getKey(),
                'failure_reason' => Str::limit($exception->getMessage(), 1000),
            ]);
            throw $exception;
        }
    }

    private function createPurchaseInvoice(CustomerAccount $account, array $data): Invoice
    {
        $plan = Plan::findOrFail($data['plan_id']);
        $amount = (float) ($data['billing_interval'] === 'yearly' ? $plan->annual_price : $plan->monthly_price);
        $legacyTaxRate = (float) $this->settings->get('payment', 'tax_rate', 0);
        $taxEnabled = filter_var($this->settings->get('tax', 'enabled', $legacyTaxRate > 0), FILTER_VALIDATE_BOOL);
        $taxRate = $taxEnabled ? (float) $this->settings->get('tax', 'default_rate', $legacyTaxRate) : 0.0;
        $pricesIncludeTax = filter_var($this->settings->get('tax', 'prices_include_tax', false), FILTER_VALIDATE_BOOL);
        $taxAmount = $pricesIncludeTax && $taxRate > 0
            ? round($amount - ($amount / (1 + $taxRate / 100)), 2)
            : round($amount * $taxRate / 100, 2);
        $unitAmount = $pricesIncludeTax ? round($amount - $taxAmount, 2) : $amount;
        return $this->invoices->create([
            'customer_account_id' => $account->getKey(), 'status' => 'open', 'currency' => $plan->currency,
            'issued_on' => today(), 'due_on' => today()->addDays((int) $this->settings->get('billing', 'payment_terms_days', 0)),
            'tax_total' => $taxAmount,
            'items' => [[
                'plan_id' => $plan->getKey(), 'description' => "{$data['workspace_name']} — {$plan->name} ({$data['billing_interval']})",
                'quantity' => 1, 'unit_amount' => $unitAmount, 'tax_total' => $taxAmount,
                'metadata' => ['workspace_purchase' => true, 'workspace_slug' => $data['slug'], 'billing_interval' => $data['billing_interval']],
            ]],
        ]);
    }

    public function requiresPaymentForPlan(Plan $plan): bool
    {
        if (! filter_var($this->settings->get('registration', 'require_payment_before_provisioning', false), FILTER_VALIDATE_BOOL)) return false;
        $trialDays = (int) ($plan->trial_days ?: $this->settings->get('trials', 'default_trial_days', 0));
        $trialWithoutPayment = filter_var($this->settings->get('trials', 'allow_trial_without_payment_method', false), FILTER_VALIDATE_BOOL);

        return ! ($trialWithoutPayment && $trialDays > 0);
    }
}
