<?php

namespace App\Services\Platform;

use App\Models\CustomerAccountActivity;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Support\Facades\DB;
use App\Services\Tenancy\TenantProvisioningService;

class SubscriptionLifecycleService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
        private readonly PlatformSettingsService $settings,
        private readonly PortalNotificationService $notifications,
        private readonly TenantProvisioningService $provisioning,
    ) {}

    public function processDue(): array
    {
        return [
            'changes' => $this->applyPendingChanges(),
            'cancellations' => $this->applyCancellations(),
            'renewals' => $this->renewSubscriptions(),
            'past_due' => $this->markPastDue(),
        ];
    }

    private function applyPendingChanges(): int
    {
        $count = 0;
        Subscription::query()->whereNotNull('pending_plan_id')->where('pending_change_effective_at', '<=', now())
            ->pluck('id')->each(function ($id) use (&$count): void {
                DB::transaction(function () use ($id, &$count): void {
                    $subscription = Subscription::query()->lockForUpdate()->find($id);
                    if (! $subscription?->pending_plan_id || $subscription->pending_change_effective_at?->isFuture()) return;
                    $oldPlan = $subscription->plan_id;
                    $oldInterval = $subscription->billing_interval;
                    $subscription->update([
                        'plan_id' => $subscription->pending_plan_id,
                        'billing_interval' => $subscription->pending_billing_interval ?: $subscription->billing_interval,
                        'pending_plan_id' => null, 'pending_billing_interval' => null, 'pending_change_effective_at' => null,
                    ]);
                    $this->subscriptions->syncTenantPlan($subscription->refresh());
                    $this->event($subscription, 'plan_change_applied', ['old_plan_id' => $oldPlan, 'new_plan_id' => $subscription->plan_id, 'old_billing_interval' => $oldInterval, 'new_billing_interval' => $subscription->billing_interval]);
                    $count++;
                });
            });
        return $count;
    }

    private function applyCancellations(): int
    {
        $count = 0;
        Subscription::query()->whereNotNull('cancel_at')->where('cancel_at', '<=', now())->pluck('id')->each(function ($id) use (&$count): void {
            DB::transaction(function () use ($id, &$count): void {
                $subscription = Subscription::query()->lockForUpdate()->find($id);
                if (! $subscription?->cancel_at || $subscription->cancel_at->isFuture()) return;
                $suspendedAt = now();
                $subscription->update([
                    'status' => 'cancelled', 'cancelled_at' => $suspendedAt, 'cancel_at' => null,
                    'metadata' => [...($subscription->metadata ?? []), 'cancellation_suspended_at' => $suspendedAt->toIso8601String()],
                ]);
                if ($subscription->tenant) $this->provisioning->suspend($subscription->tenant);
                $this->event($subscription, 'cancelled');
                $count++;
            });
        });
        return $count;
    }

    private function renewSubscriptions(): int
    {
        $count = 0;
        Subscription::query()->whereIn('status', ['active', 'trial'])->where('current_period_ends_at', '<=', now())
            ->pluck('id')->each(function ($id) use (&$count): void {
                DB::transaction(function () use ($id, &$count): void {
                    $subscription = Subscription::query()->with(['plan', 'tenant', 'customerAccount'])->lockForUpdate()->find($id);
                    if (! $subscription || ! in_array($this->status($subscription), ['active', 'trial'], true) || $subscription->current_period_ends_at?->isFuture()) return;
                    $wasTrial = $this->status($subscription) === 'trial';
                    $periodStart = $subscription->current_period_ends_at->copy();
                    $periodEnd = $subscription->billing_interval === 'yearly' ? $periodStart->copy()->addYear() : $periodStart->copy()->addMonth();
                    $amount = (float) ($subscription->billing_interval === 'yearly' ? $subscription->plan->annual_price : $subscription->plan->monthly_price);
                    $pendingProration = data_get($subscription->metadata, 'pending_proration');
                    if ($amount > 0 || abs((float) data_get($pendingProration, 'amount', 0)) >= 0.01) {
                        $this->issueRenewalInvoice($subscription, $amount, $periodStart, $periodEnd, $pendingProration);
                    }
                    $metadata = $subscription->metadata ?? [];
                    unset($metadata['pending_proration']);
                    $subscription->update([
                        'status' => 'active', 'trial_ends_at' => $wasTrial ? null : $subscription->trial_ends_at,
                        'current_period_starts_at' => $periodStart, 'current_period_ends_at' => $periodEnd,
                        'metadata' => $metadata ?: null,
                    ]);
                    $this->event($subscription, $wasTrial ? 'trial_ended' : 'renewed', ['metadata' => ['period_start' => $periodStart->toIso8601String(), 'period_end' => $periodEnd->toIso8601String()]]);
                    $this->notifications->capability($subscription->customer_account_id, 'can_manage_billing', 'subscription.renewed', $wasTrial ? 'Trial completed' : 'Subscription renewed', "{$subscription->tenant?->company_name} is active through {$periodEnd->toDateString()}.", route('portal.billing.subscriptions', absolute: false), ['subscription_id' => $subscription->getKey()], $subscription->tenant_id);
                    $count++;
                });
            });
        return $count;
    }

    private function issueRenewalInvoice(Subscription $subscription, float $amount, $periodStart, $periodEnd, ?array $pendingProration = null): Invoice
    {
        $legacyRate = (float) $this->settings->get('payment', 'tax_rate', 0);
        $enabled = filter_var($this->settings->get('tax', 'enabled', $legacyRate > 0), FILTER_VALIDATE_BOOL);
        $rate = $enabled ? (float) $this->settings->get('tax', 'default_rate', $legacyRate) : 0.0;
        $inclusive = filter_var($this->settings->get('tax', 'prices_include_tax', false), FILTER_VALIDATE_BOOL);
        $adjustment = (float) data_get($pendingProration, 'amount', 0);
        $gross = $amount + $adjustment;
        $tax = $inclusive && $rate > 0 ? round($gross - $gross / (1 + $rate / 100), 2) : round(max(0, $gross) * $rate / 100, 2);
        $baseTax = $inclusive && $rate > 0 ? round($amount - $amount / (1 + $rate / 100), 2) : round($amount * $rate / 100, 2);
        $unit = $inclusive ? round($amount - $baseTax, 2) : $amount;
        $adjustmentTax = round($tax - $baseTax, 2);
        $adjustmentUnit = $inclusive ? round($adjustment - $adjustmentTax, 2) : $adjustment;
        return $this->invoices->create([
            'idempotency_key' => hash('sha256', 'renewal|'.$subscription->getKey().'|'.$periodStart->toIso8601String()),
            'customer_account_id' => $subscription->customer_account_id, 'tenant_id' => $subscription->tenant_id,
            'status' => 'open', 'currency' => $subscription->plan->currency, 'issued_on' => $periodStart->toDateString(),
            'due_on' => $periodStart->copy()->addDays((int) $this->settings->get('billing', 'payment_terms_days', 0))->toDateString(),
            'tax_total' => $tax,
            'items' => [[
                'tenant_id' => $subscription->tenant_id, 'subscription_id' => $subscription->getKey(), 'plan_id' => $subscription->plan_id,
                'description' => "{$subscription->tenant?->company_name} — {$subscription->plan->name} ({$periodStart->toDateString()} to {$periodEnd->toDateString()})",
                'quantity' => 1, 'unit_amount' => $unit, 'tax_total' => $baseTax,
            ], ...($pendingProration && abs($adjustment) >= 0.01 ? [[
                'tenant_id' => $subscription->tenant_id, 'subscription_id' => $subscription->getKey(),
                'plan_id' => data_get($pendingProration, 'new_plan_id', $subscription->plan_id),
                'description' => data_get($pendingProration, 'description', 'Prorated plan change adjustment'),
                'quantity' => 1, 'unit_amount' => $adjustmentUnit, 'tax_total' => $adjustmentTax,
                'metadata' => ['proration' => true, 'exclude_discounts' => true, 'remaining_fraction' => data_get($pendingProration, 'remaining_fraction')],
            ]] : [])],
        ]);
    }

    private function markPastDue(): int
    {
        $cutoff = today()->subDays(max(0, (int) $this->settings->get('billing', 'grace_period_days', 7)));
        $count = 0;
        Invoice::query()->where('status', 'open')->whereDate('due_on', '<=', $cutoff)->pluck('id')->each(function ($id) use (&$count): void {
            DB::transaction(function () use ($id, &$count): void {
                $invoice = Invoice::query()->with('items.subscription')->lockForUpdate()->find($id);
                if (! $invoice || $invoice->status !== 'open') return;
                $invoice->update(['status' => 'past_due']);
                $invoice->items->pluck('subscription')->filter()->unique('id')->each(function ($subscription) use ($invoice): void {
                    $suspendedAt = now();
                    $subscription->update([
                        'status' => 'past_due', 'grace_ends_at' => $suspendedAt,
                        'metadata' => [...($subscription->metadata ?? []), 'billing_suspended_at' => $suspendedAt->toIso8601String()],
                    ]);
                    if ($subscription->tenant) $this->provisioning->suspend($subscription->tenant);
                    $this->event($subscription, 'payment_failed', ['metadata' => ['invoice_id' => $invoice->getKey()]]);
                    $this->notifications->capability($subscription->customer_account_id, 'can_manage_billing', 'billing.payment_failed', 'Invoice past due', "Payment is overdue for {$subscription->tenant?->company_name}. The workspace was suspended after the configured grace period.", route('portal.billing.invoices.show', $invoice, false), ['invoice_id' => $invoice->getKey(), 'subscription_id' => $subscription->getKey()], $subscription->tenant_id);
                });
                if ($invoice->customerAccount) $invoice->customerAccount->update(['status' => 'past_due']);
                $count++;
            });
        });
        return $count;
    }

    private function event(Subscription $subscription, string $type, array $data = []): void
    {
        SubscriptionEvent::create([...$data, 'subscription_id' => $subscription->getKey(), 'customer_account_id' => $subscription->customer_account_id, 'tenant_id' => $subscription->tenant_id, 'type' => $type, 'effective_at' => now()]);
        CustomerAccountActivity::create(['customer_account_id' => $subscription->customer_account_id, 'tenant_id' => $subscription->tenant_id, 'event' => 'subscription.'.$type, 'subject_type' => Subscription::class, 'subject_id' => (string) $subscription->getKey(), 'description' => 'Subscription '.str_replace('_', ' ', $type).'.']);
    }

    private function status(Subscription $subscription): string
    {
        return $subscription->status instanceof \BackedEnum ? $subscription->status->value : (string) $subscription->status;
    }
}
