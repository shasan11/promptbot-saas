<?php

namespace App\Services\Platform;

use App\Models\CustomerAccountActivity;
use App\Models\Plan;
use App\Models\PortalUser;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use Illuminate\Support\Facades\DB;
use App\Services\Tenancy\TenantProvisioningService;

class SubscriptionChangeService
{
    public function __construct(
        private readonly TenantProvisioningService $provisioning,
        private readonly InvoiceService $invoices,
        private readonly PlatformSettingsService $settings,
    ) {}

    public function change(Subscription $subscription, Plan $plan, string $interval, string $timing, PortalUser $actor, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $plan, $interval, $timing, $actor, $reason): Subscription {
            $subscription = Subscription::query()->with('plan')->lockForUpdate()->findOrFail($subscription->getKey());
            $oldPlan = $subscription->plan_id;
            $oldInterval = $subscription->billing_interval;
            $effectiveAt = $timing === 'immediate' ? now() : ($subscription->current_period_ends_at ?: now());
            $proration = $timing === 'immediate' ? $this->calculateProration($subscription, $plan, $interval) : null;
            $metadata = $subscription->metadata ?? [];
            if ($proration && ($proration['policy'] === 'next_invoice' || ($proration['policy'] === 'immediate' && $proration['amount'] < 0))) {
                $existing = data_get($metadata, 'pending_proration');
                if ($existing && strtoupper((string) data_get($existing, 'currency')) === $proration['currency']) {
                    $proration['amount'] = round((float) data_get($existing, 'amount', 0) + $proration['amount'], 2);
                    $proration['description'] = 'Accumulated prorated plan change adjustments';
                    $proration['idempotency_key'] = hash('sha256', data_get($existing, 'idempotency_key', '').'|'.$proration['idempotency_key']);
                }
                if (abs($proration['amount']) >= 0.01) $metadata['pending_proration'] = [...$proration, 'policy' => 'next_invoice'];
                else unset($metadata['pending_proration']);
            }

            if ($timing === 'immediate') {
                $subscription->update([
                    'plan_id' => $plan->getKey(), 'billing_interval' => $interval,
                    'pending_plan_id' => null, 'pending_billing_interval' => null, 'pending_change_effective_at' => null,
                    'metadata' => $metadata ?: null,
                ]);
                app(SubscriptionService::class)->syncTenantPlan($subscription->refresh());
                if ($proration && $proration['policy'] === 'immediate' && $proration['amount'] > 0) {
                    $this->issueProrationInvoice($subscription->refresh(), $plan, $proration);
                }
            } else {
                $subscription->update([
                    'pending_plan_id' => $plan->getKey(), 'pending_billing_interval' => $interval,
                    'pending_change_effective_at' => $effectiveAt,
                ]);
            }

            $this->event($subscription, 'plan_changed', $actor, $effectiveAt, [
                'old_plan_id' => $oldPlan, 'new_plan_id' => $plan->getKey(),
                'old_billing_interval' => $oldInterval, 'new_billing_interval' => $interval,
                'reason' => $reason, 'metadata' => ['timing' => $timing, 'proration' => $proration],
            ]);

            return $subscription->refresh();
        });
    }

    public function cancel(Subscription $subscription, PortalUser $actor, bool $immediate, string $reason, ?string $feedback = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor, $immediate, $reason, $feedback): Subscription {
            $effectiveAt = $immediate ? now() : ($subscription->current_period_ends_at ?: now());
            $metadata = $subscription->metadata ?? [];
            if ($immediate) $metadata['cancellation_suspended_at'] = now()->toIso8601String();
            $subscription->update([
                'status' => $immediate ? 'cancelled' : $subscription->status,
                'cancel_at' => $immediate ? null : $effectiveAt,
                'cancelled_at' => $immediate ? now() : null,
                'cancellation_reason' => $reason, 'cancellation_feedback' => $feedback, 'metadata' => $metadata ?: null,
            ]);
            if ($immediate && $subscription->tenant) $this->provisioning->suspend($subscription->tenant);
            $this->event($subscription, $immediate ? 'cancelled' : 'cancel_scheduled', $actor, $effectiveAt, ['reason' => $reason, 'metadata' => ['feedback' => $feedback]]);
            return $subscription->refresh();
        });
    }

    public function resume(Subscription $subscription, PortalUser $actor): Subscription
    {
        return DB::transaction(function () use ($subscription, $actor): Subscription {
            $wasCancelled = (bool) $subscription->cancelled_at;
            $metadata = $subscription->metadata ?? [];
            $cancellationSuspendedAt = isset($metadata['cancellation_suspended_at']) ? \Illuminate\Support\Carbon::parse($metadata['cancellation_suspended_at']) : null;
            unset($metadata['cancellation_suspended_at']);
            $subscription->update([
                'status' => $subscription->cancelled_at ? 'active' : $subscription->status,
                'cancel_at' => null, 'cancelled_at' => null,
                'cancellation_reason' => null, 'cancellation_feedback' => null, 'metadata' => $metadata ?: null,
            ]);
            if ($wasCancelled && $cancellationSuspendedAt && $subscription->tenant?->suspended_at
                && $subscription->tenant->suspended_at->lessThanOrEqualTo($cancellationSuspendedAt->copy()->addSeconds(5))) {
                $this->provisioning->activate($subscription->tenant);
            }
            $this->event($subscription, 'resumed', $actor, now());
            return $subscription->refresh();
        });
    }

    private function event(Subscription $subscription, string $type, PortalUser $actor, mixed $effectiveAt, array $data = []): void
    {
        SubscriptionEvent::create([
            ...$data, 'subscription_id' => $subscription->getKey(),
            'customer_account_id' => $subscription->customer_account_id, 'tenant_id' => $subscription->tenant_id,
            'type' => $type, 'actor_type' => PortalUser::class, 'actor_id' => (string) $actor->getKey(),
            'effective_at' => $effectiveAt,
        ]);
        CustomerAccountActivity::create([
            'customer_account_id' => $subscription->customer_account_id, 'tenant_id' => $subscription->tenant_id,
            'actor_type' => PortalUser::class, 'actor_id' => (string) $actor->getKey(),
            'event' => 'subscription.'.$type, 'subject_type' => Subscription::class,
            'subject_id' => (string) $subscription->getKey(), 'description' => 'Subscription '.str_replace('_', ' ', $type).'.',
        ]);
        app(PortalNotificationService::class)->capability(
            $subscription->customer_account_id,
            'can_manage_billing',
            'subscription.'.$type,
            'Subscription '.str_replace('_', ' ', $type),
            ($subscription->tenant?->company_name ?: 'Your workspace').' subscription was '.str_replace('_', ' ', $type).'.',
            route('portal.billing.subscriptions', absolute: false),
            data: ['subscription_id' => $subscription->getKey(), 'effective_at' => (string) $effectiveAt],
            tenantId: $subscription->tenant_id,
        );
    }

    private function calculateProration(Subscription $subscription, Plan $newPlan, string $newInterval): ?array
    {
        $policy = (string) $this->settings->get('billing', 'proration_policy', 'next_invoice');
        if ($policy === 'none' || ! $subscription->current_period_starts_at || ! $subscription->current_period_ends_at
            || $subscription->current_period_ends_at->isPast()) return null;
        $oldPlan = $subscription->plan ?: Plan::query()->find($subscription->plan_id);
        if (! $oldPlan) return null;
        $oldAmount = (float) ($subscription->billing_interval === 'yearly' ? $oldPlan->annual_price : $oldPlan->monthly_price);
        $newAmount = (float) ($newInterval === 'yearly' ? $newPlan->annual_price : $newPlan->monthly_price);
        $periodSeconds = max(1, $subscription->current_period_starts_at->diffInSeconds($subscription->current_period_ends_at));
        $remainingSeconds = max(0, now()->diffInSeconds($subscription->current_period_ends_at, false));
        $fraction = min(1, $remainingSeconds / $periodSeconds);
        $newIntervalSeconds = $newInterval === 'yearly' ? 365 * 86400 : 30 * 86400;
        $oldCredit = $oldAmount * $fraction;
        $newCharge = $newAmount * ($remainingSeconds / $newIntervalSeconds);
        $amount = round($newCharge - $oldCredit, 2);
        if (abs($amount) < 0.01) return null;

        return [
            'policy' => $policy, 'amount' => $amount, 'currency' => strtoupper($newPlan->currency),
            'description' => 'Prorated plan change adjustment', 'old_plan_id' => $oldPlan->getKey(),
            'new_plan_id' => $newPlan->getKey(), 'remaining_fraction' => round($fraction, 6),
            'calculated_at' => now()->toIso8601String(),
            'idempotency_key' => hash('sha256', 'proration|'.$subscription->getKey().'|'.$oldPlan->getKey().'|'.$newPlan->getKey().'|'.$newInterval.'|'.$subscription->current_period_ends_at->toIso8601String().'|'.$subscription->updated_at?->toIso8601String()),
        ];
    }

    private function issueProrationInvoice(Subscription $subscription, Plan $plan, array $proration): void
    {
        $legacyRate = (float) $this->settings->get('payment', 'tax_rate', 0);
        $rate = filter_var($this->settings->get('tax', 'enabled', $legacyRate > 0), FILTER_VALIDATE_BOOL) ? (float) $this->settings->get('tax', 'default_rate', $legacyRate) : 0.0;
        $inclusive = filter_var($this->settings->get('tax', 'prices_include_tax', false), FILTER_VALIDATE_BOOL);
        $gross = (float) $proration['amount'];
        $tax = $inclusive && $rate > 0 ? round($gross - $gross / (1 + $rate / 100), 2) : round($gross * $rate / 100, 2);
        $unit = $inclusive ? round($gross - $tax, 2) : $gross;
        $this->invoices->create([
            'idempotency_key' => $proration['idempotency_key'], 'customer_account_id' => $subscription->customer_account_id,
            'tenant_id' => $subscription->tenant_id, 'status' => 'open', 'currency' => $proration['currency'], 'issued_on' => today(),
            'due_on' => today()->addDays((int) $this->settings->get('billing', 'payment_terms_days', 0)), 'tax_total' => $tax,
            'items' => [[
                'tenant_id' => $subscription->tenant_id, 'subscription_id' => $subscription->getKey(), 'plan_id' => $plan->getKey(),
                'description' => $proration['description'], 'quantity' => 1, 'unit_amount' => $unit, 'tax_total' => $tax,
                'metadata' => ['proration' => true, 'exclude_discounts' => true, 'remaining_fraction' => $proration['remaining_fraction']],
            ]],
        ]);
    }
}
