<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\PortalUser;

class CustomerPortalService
{
    public function overview(CustomerAccount $account, ?PortalUser $user = null): array
    {
        $tenantQuery = $user ? $account->tenantsVisibleTo($user) : $account->tenants();
        $visibleTenantIds = (clone $tenantQuery)->pluck('tenants.id');
        $membership = $user ? $account->users()->where('portal_users.id', $user->getKey())->first()?->pivot : null;
        $restricted = $membership && $membership->role !== 'owner' && $membership->service_access === 'selected';
        $activeSubscriptions = $account->subscriptions()->whereIn('tenant_id', $visibleTenantIds)->with(['plan', 'tenant.domains'])
            ->whereIn('status', ['active', 'trial', 'past_due'])->get();

        return [
            'metrics' => [
                'activeWorkspaces' => (clone $tenantQuery)->whereIn('status', ['active', 'provisioning', 'trial'])->count(),
                'monthlyBilling' => round($activeSubscriptions->sum(fn (Subscription $subscription) => $this->monthlyValue($subscription)), 2),
                'outstandingBalance' => (float) $this->visibleInvoices($account, $restricted, $visibleTenantIds)->whereIn('status', ['open', 'past_due'])->sum('total'),
                'openSupportTickets' => $account->supportTickets()->where(fn ($query) => $query->whereNull('tenant_id')->orWhereIn('tenant_id', $visibleTenantIds))->whereNotIn('status', ['resolved', 'closed'])->count(),
            ],
            'workspaces' => (clone $tenantQuery)->with(['plan', 'domains', 'subscriptions.plan'])->latest()->limit(6)->get(),
            'recentInvoices' => $this->visibleInvoices($account, $restricted, $visibleTenantIds)->latest('issued_on')->limit(5)->get(),
            'recentPayments' => $this->visiblePayments($account, $restricted, $visibleTenantIds)->latest()->limit(5)->get(),
            'recentActivity' => $account->activities()->where('is_customer_visible', true)->limit(8)->get(),
        ];
    }

    public function billing(CustomerAccount $account, ?PortalUser $user = null): array
    {
        $membership = $user ? $account->users()->where('portal_users.id', $user->getKey())->first()?->pivot : null;
        $restricted = $membership && $membership->role !== 'owner' && $membership->service_access === 'selected';
        $visibleTenantIds = $user ? $account->tenantsVisibleTo($user)->pluck('tenants.id') : collect();
        $subscriptions = $account->subscriptions()->when($restricted, fn ($query) => $query->whereIn('tenant_id', $visibleTenantIds))->with(['plan', 'tenant'])->latest()->get();

        return [
            'subscriptions' => $subscriptions,
            'monthlyRecurring' => round($subscriptions->filter(fn (Subscription $subscription) => $this->isRecurring($subscription))->sum(fn (Subscription $subscription) => $this->monthlyValue($subscription)), 2),
            'outstandingBalance' => (float) $this->visibleInvoices($account, $restricted, $visibleTenantIds)->whereIn('status', ['open', 'past_due'])->sum('total'),
            'nextBillingDate' => $subscriptions->whereIn('status', ['active', 'trial'])->min('current_period_ends_at'),
            'defaultPaymentMethod' => $account->paymentMethods()->where('is_default', true)->first(),
        ];
    }

    public function monthlyValue(Subscription $subscription): float
    {
        if (! $subscription->plan) return 0;

        return $subscription->billing_interval === 'yearly'
            ? round((float) $subscription->plan->annual_price / 12, 2)
            : (float) $subscription->plan->monthly_price;
    }

    private function isRecurring(Subscription $subscription): bool
    {
        $status = $subscription->status instanceof \BackedEnum ? $subscription->status->value : $subscription->status;

        return in_array($status, ['active', 'trial', 'past_due'], true);
    }

    private function visibleInvoices(CustomerAccount $account, bool $restricted, $visibleTenantIds)
    {
        return $account->invoices()->when($restricted, fn ($query) => $query->where(fn ($scope) => $scope
            ->whereIn('tenant_id', $visibleTenantIds)
            ->orWhere(fn ($consolidated) => $consolidated->whereNull('tenant_id')
                ->whereDoesntHave('items', fn ($items) => $items->whereNotNull('tenant_id')->whereNotIn('tenant_id', $visibleTenantIds)))));
    }

    private function visiblePayments(CustomerAccount $account, bool $restricted, $visibleTenantIds)
    {
        return $account->payments()->when($restricted, fn ($query) => $query->where(fn ($scope) => $scope
            ->whereIn('tenant_id', $visibleTenantIds)
            ->orWhere(fn ($accountPayment) => $accountPayment->whereNull('tenant_id')->whereHas('invoice', fn ($invoice) => $invoice
                ->where(fn ($visibleInvoice) => $visibleInvoice->whereIn('tenant_id', $visibleTenantIds)
                    ->orWhere(fn ($consolidated) => $consolidated->whereNull('tenant_id')
                        ->whereDoesntHave('items', fn ($items) => $items->whereNotNull('tenant_id')->whereNotIn('tenant_id', $visibleTenantIds))))))));
    }
}
