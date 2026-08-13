<?php

namespace App\Http\Controllers\Portal;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Payment;
use App\Models\PortalPaymentMethod;
use App\Models\CustomerAccountActivity;
use App\Services\Platform\CustomerPortalService;
use App\Services\Platform\PlatformSettingsService;
use App\Services\Platform\SubscriptionChangeService;
use App\Services\Platform\CouponService;
use App\Services\Platform\InvoicePdfService;
use App\Services\Platform\PaymentAttemptService;
use App\Services\Platform\PublicPlanService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends PortalController
{
    public function overview(Request $request, CustomerPortalService $portal): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        return Inertia::render('Portal/Billing/Overview', $portal->billing($account, $request->user('portal')));
    }

    public function subscriptions(Request $request, PublicPlanService $plans, PlatformSettingsService $settings): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        $workspaceId = $request->string('workspace')->toString();
        if ($workspaceId !== '') abort_unless(in_array($workspaceId, $this->visibleTenantIds($request), true), 404);
        return Inertia::render('Portal/Billing/Subscriptions', [
            'subscriptions' => $account->subscriptions()->when($this->selectedWorkspaceAccess($request), fn ($query) => $query->whereIn('tenant_id', $this->visibleTenantIds($request)))
                ->when($workspaceId !== '', fn ($query) => $query->where('tenant_id', $workspaceId))->with(['tenant', 'plan', 'pendingPlan', 'coupon'])->latest()->paginate(20)->withQueryString(),
            'plans' => $plans->query()->orderBy('sort_order')->get(),
            'workspaceFilter' => $workspaceId,
            'planChangePolicy' => $settings->get('billing', 'plan_change_policy', 'customer_choice'),
            'allowImmediateCancellation' => filter_var($settings->get('billing', 'allow_immediate_cancellation', false), FILTER_VALIDATE_BOOL),
            'allowPlanChanges' => filter_var($settings->get('customer_portal', 'allow_plan_changes', true), FILTER_VALIDATE_BOOL),
            'allowCancellations' => filter_var($settings->get('customer_portal', 'allow_cancellations', true), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function invoices(Request $request): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        return Inertia::render('Portal/Billing/Invoices', [
            'invoices' => $this->scopeVisibleInvoices($account->invoices(), $request)->withCount('items')->with('tenant')->latest('issued_on')->paginate(20),
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): Response
    {
        $this->authorize('view', $invoice);
        return Inertia::render('Portal/Billing/Invoice', ['invoice' => $invoice->load(['items.tenant', 'payments', 'customerAccount'])]);
    }

    public function downloadInvoice(Request $request, Invoice $invoice, InvoicePdfService $pdf): HttpResponse
    {
        $this->authorize('view', $invoice);
        $invoice->load(['items', 'customerAccount']);
        return response($pdf->make($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="invoice-'.$invoice->number.'.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function payInvoice(Request $request, Invoice $invoice, PaymentAttemptService $attempts): RedirectResponse
    {
        $this->authorize('view', $invoice);
        abort_if(in_array($invoice->status, ['paid', 'void'], true), 422, 'This invoice cannot be paid.');
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $attempt = $attempts->forInvoice($invoice->customerAccount, $invoice, $data['idempotency_key']);
        return back()->with('status', data_get($attempt->metadata, 'instruction', 'Payment attempt created.'));
    }

    public function retryPayment(Request $request, Payment $payment, PaymentAttemptService $attempts): RedirectResponse
    {
        $this->authorize('view', $payment);
        abort_unless($payment->status === 'failed' && $payment->invoice, 422, 'Only failed invoice payments can be retried.');
        $data = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $attempt = $attempts->forInvoice($payment->customerAccount, $payment->invoice, $data['idempotency_key'], $payment);
        return back()->with('status', data_get($attempt->metadata, 'instruction', 'Payment retry created.'));
    }

    public function payments(Request $request): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        return Inertia::render('Portal/Billing/Payments', [
            'payments' => $this->scopeVisiblePayments($account->payments(), $request)->with(['invoice', 'tenant'])->latest()->paginate(20),
        ]);
    }

    public function paymentMethods(Request $request): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        $gateway = app(PlatformSettingsService::class)->get('payment', 'default_gateway', 'manual');
        return Inertia::render('Portal/Billing/PaymentMethods', ['paymentMethods' => $account->paymentMethods()->get(), 'gateway' => $gateway]);
    }

    public function storePaymentMethod(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $account = $this->account($request); $this->authorize('manageBilling', $account);
        $provider = (string) $settings->get('payment', 'default_gateway', 'manual');
        abort_if($provider === 'manual', 422, 'The manual gateway does not support saved payment methods.');
        $data = $request->validate([
            'provider_reference' => ['required', 'string', 'max:500'], 'type' => ['required', 'in:card,bank,wallet'],
            'brand' => ['nullable', 'string', 'max:50'], 'last_four' => ['nullable', 'digits:4'],
            'expires_month' => ['nullable', 'integer', 'between:1,12'], 'expires_year' => ['nullable', 'integer', 'between:2020,2200'],
        ]);
        DB::transaction(function () use ($account, $provider, $data): void {
            $makeDefault = ! $account->paymentMethods()->where('is_default', true)->exists();
            $account->paymentMethods()->create([...$data, 'provider' => $provider, 'is_default' => $makeDefault]);
        });
        CustomerAccountActivity::create(['customer_account_id' => $account->id, 'actor_type' => $request->user('portal')::class, 'actor_id' => (string) $request->user('portal')->id, 'event' => 'billing.payment_method_added', 'description' => ucfirst($data['type']).' payment method added via '.$provider.'.']);
        return back()->with('status', 'Payment method added.');
    }

    public function defaultPaymentMethod(Request $request, PortalPaymentMethod $method): RedirectResponse
    {
        $account = $this->account($request); $this->authorize('manageBilling', $account);
        abort_unless((int) $method->customer_account_id === (int) $account->id, 404);
        DB::transaction(function () use ($account, $method): void { $account->paymentMethods()->update(['is_default' => false]); $method->update(['is_default' => true]); });
        return back()->with('status', 'Default payment method updated.');
    }

    public function destroyPaymentMethod(Request $request, PortalPaymentMethod $method): RedirectResponse
    {
        $account = $this->account($request); $this->authorize('manageBilling', $account);
        abort_unless((int) $method->customer_account_id === (int) $account->id, 404);
        $wasDefault = $method->is_default; $method->delete();
        if ($wasDefault) { $account->paymentMethods()->oldest()->first()?->update(['is_default' => true]); }
        return back()->with('status', 'Payment method removed.');
    }

    public function profile(Request $request): Response
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        return Inertia::render('Portal/Billing/Profile', ['billingProfile' => $account->billingProfile]);
    }

    public function updateProfile(Request $request, PlatformSettingsService $settings): RedirectResponse
    {
        $account = $this->account($request);
        $this->authorize('manageBilling', $account);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_billing_profile_updates', true), FILTER_VALIDATE_BOOL), 403);
        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:255'], 'billing_email' => ['required', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'], 'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'], 'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'], 'country' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'postal_code' => ['required', 'string', 'max:30'], 'tax_number' => ['nullable', 'string', 'max:100'],
            'vat_number' => ['nullable', 'string', 'max:100'], 'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
        ]);
        $data['country'] = strtoupper($data['country']); $data['currency'] = strtoupper($data['currency']);
        $account->billingProfile()->updateOrCreate(['customer_account_id' => $account->getKey()], $data);
        CustomerAccountActivity::create([
            'customer_account_id' => $account->getKey(), 'actor_type' => $request->user('portal')::class,
            'actor_id' => (string) $request->user('portal')->getKey(), 'event' => 'billing.profile_updated',
            'description' => 'The account billing profile was updated.',
        ]);
        return back()->with('status', 'Billing profile updated. New invoices will use this identity.');
    }

    public function changeSubscription(Request $request, Subscription $subscription, SubscriptionChangeService $changes, PlatformSettingsService $settings, PublicPlanService $plans): RedirectResponse
    {
        $this->authorize('update', $subscription);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_plan_changes', true), FILTER_VALIDATE_BOOL), 403);
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'timing' => ['required', 'in:immediate,period_end'], 'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $plan = $plans->query()->whereKey($data['plan_id'])->firstOrFail();
        $policy = (string) $settings->get('billing', 'plan_change_policy', 'customer_choice');
        if (in_array($policy, ['immediate', 'period_end'], true)) $data['timing'] = $policy;
        $changes->change($subscription, $plan, $data['billing_interval'], $data['timing'], $request->user('portal'), $data['reason'] ?? null);
        return back()->with('status', $data['timing'] === 'immediate' ? 'Subscription updated.' : 'Subscription change scheduled.');
    }

    public function cancelSubscription(Request $request, Subscription $subscription, SubscriptionChangeService $changes, PlatformSettingsService $settings): RedirectResponse
    {
        $this->authorize('update', $subscription);
        abort_unless(filter_var($settings->get('customer_portal', 'allow_cancellations', true), FILTER_VALIDATE_BOOL), 403);
        $data = $request->validate(['immediate' => ['boolean'], 'reason' => ['required', 'string', 'max:1000'], 'feedback' => ['nullable', 'string', 'max:3000']]);
        $allowImmediate = filter_var($settings->get('billing', 'allow_immediate_cancellation', false), FILTER_VALIDATE_BOOL);
        $changes->cancel($subscription, $request->user('portal'), $allowImmediate && ($data['immediate'] ?? false), $data['reason'], $data['feedback'] ?? null);
        return back()->with('status', 'Cancellation recorded.');
    }

    public function resumeSubscription(Request $request, Subscription $subscription, SubscriptionChangeService $changes): RedirectResponse
    {
        $this->authorize('update', $subscription);
        $changes->resume($subscription, $request->user('portal'));
        return back()->with('status', 'Subscription resumed.');
    }

    public function applyCoupon(Request $request, Subscription $subscription, CouponService $coupons): RedirectResponse
    {
        $this->authorize('update', $subscription);
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);
        $coupon = $coupons->apply($subscription, $data['code']);
        return back()->with('status', "Coupon {$coupon->code} applied.");
    }

    public function removeCoupon(Request $request, Subscription $subscription, CouponService $coupons): RedirectResponse
    {
        $this->authorize('update', $subscription);
        $coupons->remove($subscription);
        return back()->with('status', 'Coupon removed.');
    }

    private function scopeVisibleInvoices($query, Request $request)
    {
        if (! $this->selectedWorkspaceAccess($request)) return $query;
        $ids = $this->visibleTenantIds($request);

        return $query->where(fn ($scope) => $scope->whereIn('tenant_id', $ids)
            ->orWhere(fn ($consolidated) => $consolidated->whereNull('tenant_id')
                ->whereDoesntHave('items', fn ($items) => $items->whereNotNull('tenant_id')->whereNotIn('tenant_id', $ids))));
    }

    private function scopeVisiblePayments($query, Request $request)
    {
        if (! $this->selectedWorkspaceAccess($request)) return $query;
        $ids = $this->visibleTenantIds($request);

        return $query->where(fn ($scope) => $scope->whereIn('tenant_id', $ids)
            ->orWhere(fn ($accountPayment) => $accountPayment->whereNull('tenant_id')->whereHas('invoice', fn ($invoice) => $invoice
                ->where(fn ($visibleInvoice) => $visibleInvoice->whereIn('tenant_id', $ids)
                    ->orWhere(fn ($consolidated) => $consolidated->whereNull('tenant_id')
                        ->whereDoesntHave('items', fn ($items) => $items->whereNotNull('tenant_id')->whereNotIn('tenant_id', $ids)))))));
    }
}
