<?php

namespace App\Http\Controllers\Portal;

use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\PublicPlanService;
use App\Services\Platform\WorkspacePurchaseService;
use App\Services\Platform\TenantUsageService;
use App\Services\Platform\AccountLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\WorkspacePurchaseRequest;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkspaceController extends PortalController
{
    public function index(Request $request, TenantUsageService $usage): Response
    {
        $account = $this->account($request);
        $this->authorize('view', $account);
        $workspaces = $account->tenantsVisibleTo($request->user('portal'))->with(['plan', 'domains', 'subscriptions.plan'])
            ->when($request->string('status')->isNotEmpty(), function ($query) use ($request): void {
                $status = $request->string('status')->toString();
                if (in_array($status, ['trial', 'cancelled'], true)) $query->whereHas('subscriptions', fn ($subscriptions) => $subscriptions->where('status', $status));
                else $query->where('status', $status);
            })
            ->latest()->paginate(12)->withQueryString();
        $workspaces->through(function (Tenant $workspace) use ($usage): Tenant {
            $workspace->setAttribute('usage_summary', $usage->snapshot($workspace));
            return $workspace;
        });

        return Inertia::render('Portal/Workspaces/Index', ['workspaces' => $workspaces, 'filters' => $request->only('status')]);
    }

    public function create(Request $request, PlatformSettingsService $settings, PublicPlanService $plans): Response
    {
        $account = $this->account($request);
        $this->authorize('manageServices', $account);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_workspace_creation', true), FILTER_VALIDATE_BOOL), 403);

        $paymentRequired = filter_var($settings->get('registration', 'require_payment_before_provisioning', false), FILTER_VALIDATE_BOOL);
        $provisioningMode = (string) $settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode', 'manual'));
        return Inertia::render('Portal/Workspaces/Create', [
            'plans' => $plans->query()->orderBy('sort_order')->get(),
            'selection' => $request->session()->pull('portal.purchase_selection', []),
            'tenantBaseDomain' => config('saas.tenant_base_domain'),
            'paymentRequired' => $paymentRequired,
            'allowTrialWithoutPayment' => filter_var($settings->get('trials', 'allow_trial_without_payment_method', false), FILTER_VALIDATE_BOOL),
            'defaultTrialDays' => (int) $settings->get('trials', 'default_trial_days', 0),
            'defaultRegion' => (string) $settings->get('tenant_provisioning', 'default_region', ''),
            'billingProfileReady' => ! $paymentRequired || (bool) ($account->billingProfile?->billing_name && $account->billingProfile?->billing_email && $account->billingProfile?->address_line_1 && $account->billingProfile?->country),
            'provisioningAvailable' => in_array($provisioningMode, ['mysql', 'cpanel'], true),
        ]);
    }

    public function store(Request $request, WorkspacePurchaseService $purchases, PlatformSettingsService $settings, AccountLimitService $limits, PublicPlanService $plans): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageServices', $account);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_workspace_creation', true), FILTER_VALIDATE_BOOL), 403);
        abort_unless(! filter_var($settings->get('maintenance', 'block_new_provisioning', false), FILTER_VALIDATE_BOOL), 503, 'New workspace provisioning is temporarily paused for maintenance.');
        $provisioningMode = (string) $settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode', 'manual'));
        abort_unless(in_array($provisioningMode, ['mysql', 'cpanel'], true), 503, 'Self-service workspace creation requires an automatic database provisioning mode. Contact platform support.');
        $maximum = (int) $settings->get('registration', 'maximum_workspaces_per_account', 0);
        abort_if($limits->reached($account, 'workspaces', $account->tenants()->count(), $maximum), 422, 'This account has reached its workspace limit.');

        $data = $request->validate([
            'workspace_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:60', 'unique:tenants,slug'],
            'region' => ['nullable', 'string', 'max:100'],
            'plan_id' => ['required', Rule::exists('plans', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_public', true))],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'create_tenant_owner' => ['boolean'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);
        abort_unless($plans->allows((int) $data['plan_id']), 422, 'The selected plan is not available for customer provisioning.');
        $plan = Plan::findOrFail($data['plan_id']);
        if ($purchases->requiresPaymentForPlan($plan)) {
            $profile = $account->billingProfile;
            if (! $profile?->billing_name || ! $profile?->billing_email || ! $profile?->address_line_1 || ! $profile?->country) {
                throw ValidationException::withMessages(['billing_profile' => 'Complete the account billing profile before purchasing a workspace.']);
            }
        }
        $data['provisioning_mode'] = $provisioningMode;

        try {
            $result = $purchases->purchase($account, $request->user('portal'), $data, $data['idempotency_key']);
        } catch (Throwable $exception) {
            report($exception);
            $failed = $account->tenants()->where('slug', $data['slug'])->first();
            $redirect = $failed ? redirect()->route('portal.workspaces.show', $failed) : redirect()->route('portal.workspaces.index');
            return $redirect->with('error', 'We could not finish this workspace setup. Your account and billing information are safe, and the setup can be retried.');
        }
        if ($result instanceof WorkspacePurchaseRequest) {
            return redirect()->route('portal.billing.invoices.show', $result->invoice_id)->with('status', 'Your workspace order is ready. Complete payment to begin provisioning.');
        }

        return redirect()->route('portal.workspaces.show', $result)->with('status', 'Workspace created successfully.');
    }

    public function show(Request $request, Tenant $workspace, TenantUsageService $usage, PlatformSettingsService $settings): Response
    {
        $this->authorize('view', $workspace);
        $account = $this->account($request);
        $grantedUserIds = DB::table('customer_account_user_tenants')->where('customer_account_id', $account->getKey())
            ->where('tenant_id', $workspace->getKey())->pluck('portal_user_id');
        $members = $account->users()->orderBy('name')->get()->filter(fn ($member) => $member->pivot->role === 'owner'
            || $member->pivot->service_access === 'all' || $grantedUserIds->contains($member->getKey()))->values();

        return Inertia::render('Portal/Workspaces/Show', [
            'workspace' => $workspace->load(['plan.features', 'domains', 'subscriptions.plan', 'provisioningLogs']),
            'usage' => $usage->snapshot($workspace),
            'members' => $members,
            'capabilities' => [
                'billing' => $request->user('portal')->can('manageBilling', $account),
                'support' => $request->user('portal')->can('manageSupport', $account),
                'retryProvisioning' => $request->user('portal')->can('manageServices', $account)
                    && filter_var($settings->get('tenant_provisioning', 'customer_can_retry', false), FILTER_VALIDATE_BOOL)
                    && in_array((string) $settings->get('tenant_provisioning', 'mode', config('saas.db_provisioning_mode', 'manual')), ['mysql', 'cpanel'], true),
            ],
        ]);
    }

    public function retry(Request $request, Tenant $workspace, WorkspacePurchaseService $purchases, PlatformSettingsService $settings): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageServices', $account);
        $this->authorize('view', $workspace);
        abort_unless(filter_var($settings->get('tenant_provisioning', 'customer_can_retry', false), FILTER_VALIDATE_BOOL), 403);
        abort_unless($workspace->last_provisioning_error, 422, 'This workspace does not need a provisioning retry.');

        try {
            $purchases->retryFailed($workspace, $request->user('portal'));
            return back()->with('status', 'Workspace provisioning completed successfully.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'The setup retry did not complete. Our platform team can continue from the recorded provisioning state.');
        }
    }
}
