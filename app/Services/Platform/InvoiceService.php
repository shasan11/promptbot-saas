<?php

namespace App\Services\Platform;

use App\Models\Invoice;
use App\Models\CustomerAccount;
use App\Models\Tenant;
use App\Models\Subscription;
use App\Models\CouponRedemption;
use App\Models\CustomerAccountActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Tenancy\TenantProvisioningService;

class InvoiceService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly PortalNotificationService $notifications,
        private readonly TenantProvisioningService $provisioning,
    ) {}

    public function create(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            if (filled($data['idempotency_key'] ?? null)) {
                $existing = Invoice::query()->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($existing) return $existing;
            }
            $items = collect($data['items']);
            $subscriptions = Subscription::with('coupon')->whereIn('id', $items->pluck('subscription_id')->filter())->get()->keyBy('id');
            $fixedApplied = [];
            $calculatedItems = $items->map(function (array $item) use ($subscriptions, &$fixedApplied, $data): array {
                $subtotal = round($item['quantity'] * ($item['unit_amount'] ?? $item['unit_price']), 2);
                $discount = 0.0;
                $subscription = isset($item['subscription_id']) ? $subscriptions->get($item['subscription_id']) : null;
                $coupon = $subscription?->coupon;
                if (! data_get($item, 'metadata.exclude_discounts') && $coupon && $coupon->is_active && (! $coupon->starts_at || $coupon->starts_at->isPast()) && (! $coupon->expires_at || $coupon->expires_at->isFuture())) {
                    $duration = $coupon->metadata['duration'] ?? 'once';
                    $prior = CouponRedemption::where('coupon_id', $coupon->id)->where('subscription_id', $subscription->id)->where('status', 'redeemed')->count();
                    $eligible = ($duration === 'forever' || ($duration === 'once' && $prior === 0) || ($duration === 'repeating' && $prior < (int) ($coupon->metadata['duration_months'] ?? 1)))
                        && ($coupon->minimum_amount === null || $subtotal >= (float) $coupon->minimum_amount)
                        && ($coupon->billing_interval === null || $coupon->billing_interval === $subscription->billing_interval);
                    if ($eligible && $coupon->type === 'percent') $discount = round($subtotal * min(100, (float) $coupon->value) / 100, 2);
                    if ($eligible && $coupon->type === 'fixed' && empty($fixedApplied[$subscription->id]) && strtoupper($coupon->metadata['currency'] ?? $data['currency']) === strtoupper($data['currency'])) {
                        $discount = min($subtotal, (float) $coupon->value);
                        $fixedApplied[$subscription->id] = true;
                    }
                }
                return [...$item, '_subtotal' => $subtotal, '_discount' => $discount, '_coupon' => $coupon, '_subscription' => $subscription];
            });
            $subtotal = $calculatedItems->sum('_subtotal');
            $discountTotal = $calculatedItems->sum('_discount');
            $explicitItemTax = $items->contains(fn (array $item) => array_key_exists('tax_total', $item));
            $taxTotal = round((float) ($explicitItemTax ? $items->sum('tax_total') : ($data['tax_total'] ?? 0)), 2);
            $taxBase = max(0.0, (float) $subtotal - (float) $discountTotal);
            $allocatedTax = 0.0;
            $lastIndex = max(0, $calculatedItems->count() - 1);
            $calculatedItems = $calculatedItems->values()->map(function (array $item, int $index) use ($explicitItemTax, $taxTotal, $taxBase, &$allocatedTax, $lastIndex): array {
                if ($explicitItemTax) $itemTax = round((float) ($item['tax_total'] ?? 0), 2);
                elseif ($index === $lastIndex) $itemTax = round($taxTotal - $allocatedTax, 2);
                else $itemTax = $taxBase > 0 ? round($taxTotal * max(0, $item['_subtotal'] - $item['_discount']) / $taxBase, 2) : 0.0;
                $allocatedTax += $itemTax;
                return [...$item, '_tax' => $itemTax];
            });
            $tenant = isset($data['tenant_id']) ? Tenant::findOrFail($data['tenant_id']) : null;
            $isWorkspacePurchase = $items->contains(fn (array $item) => (bool) data_get($item, 'metadata.workspace_purchase'));
            if (! $tenant && ! $isWorkspacePurchase && $this->settings->get('billing', 'billing_mode_support', 'both') === 'per_service') {
                throw new \InvalidArgumentException('Consolidated account invoices are disabled by billing policy.');
            }
            $account = CustomerAccount::with('billingProfile')->findOrFail($data['customer_account_id'] ?? $tenant?->customer_account_id);
            if ($tenant && (int) $tenant->customer_account_id !== (int) $account->getKey()) {
                throw new \InvalidArgumentException('Invoice tenant and customer account do not match.');
            }
            $itemTenantIds = $items->pluck('tenant_id')->filter()->unique();
            if ($tenant && $itemTenantIds->contains(fn ($tenantId) => (string) $tenantId !== (string) $tenant->getKey())) {
                throw new \InvalidArgumentException('A service-specific invoice cannot contain another workspace.');
            }
            if ($itemTenantIds->isNotEmpty() && Tenant::query()->whereIn('id', $itemTenantIds)->where('customer_account_id', '!=', $account->getKey())->exists()) {
                throw new \InvalidArgumentException('Invoice line item tenant and customer account do not match.');
            }

            $invoice = Invoice::create([
                'customer_account_id' => $account->getKey(),
                'tenant_id' => $tenant?->getKey(),
                'number' => $this->nextNumber(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'status' => $data['status'],
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'total' => max(0, $subtotal - $discountTotal + $taxTotal),
                'currency' => strtoupper($data['currency']),
                'issued_on' => $data['issued_on'],
                'due_on' => $data['due_on'] ?? null,
                'billing_snapshot' => $this->billingSnapshot($account),
            ]);
            $redemptions = [];
            foreach ($calculatedItems->filter(fn ($item) => $item['_coupon'] && $item['_discount'] > 0)->groupBy(fn ($item) => $item['_coupon']->id.'|'.$item['_subscription']->id) as $group) {
                $sample = $group->first();
                $redemption = CouponRedemption::create([
                    'coupon_id' => $sample['_coupon']->id, 'customer_account_id' => $account->getKey(),
                    'subscription_id' => $sample['_subscription']->id, 'invoice_id' => $invoice->id,
                    'code_snapshot' => $sample['_coupon']->code, 'discount_amount' => $group->sum('_discount'),
                    'currency' => strtoupper($data['currency']), 'status' => 'redeemed', 'redeemed_at' => now(),
                ]);
                $redemptions[$sample['_subscription']->id] = $redemption;
                $duration = $sample['_coupon']->metadata['duration'] ?? 'once';
                $applied = CouponRedemption::where('coupon_id', $sample['_coupon']->id)->where('subscription_id', $sample['_subscription']->id)->where('status', 'redeemed')->count();
                if ($duration === 'once' || ($duration === 'repeating' && $applied >= (int) ($sample['_coupon']->metadata['duration_months'] ?? 1))) {
                    $sample['_subscription']->update(['coupon_id' => null]);
                }
            }

            foreach ($calculatedItems as $item) {
                $invoice->items()->create([
                    'tenant_id' => $item['tenant_id'] ?? $tenant?->getKey(),
                    'subscription_id' => $item['subscription_id'] ?? null,
                    'plan_id' => $item['plan_id'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_amount' => $item['unit_amount'] ?? $item['unit_price'],
                    'subtotal' => $item['_subtotal'],
                    'discount_total' => $item['_discount'],
                    'coupon_redemption_id' => $redemptions[$item['subscription_id'] ?? null]->id ?? null,
                    'tax_total' => $item['_tax'],
                    'total' => round($item['_subtotal'] - $item['_discount'] + $item['_tax'], 2),
                    'metadata' => $item['metadata'] ?? null,
                ]);
            }

            if ($invoice->status === 'open') {
                $this->notifications->capability($account, 'can_manage_billing', 'billing.invoice_issued', "Invoice {$invoice->number} issued", "{$invoice->currency} ".number_format((float) $invoice->total, 2).' is due'.($invoice->due_on ? ' by '.$invoice->due_on->toDateString() : '.'), route('portal.billing.invoices.show', $invoice, false), ['invoice_id' => $invoice->getKey(), 'invoice_number' => $invoice->number, 'invoice_total' => $invoice->currency.' '.number_format((float) $invoice->total, 2)], $invoice->tenant_id);
            }

            return $invoice;
        });
    }

    public function markPaid(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') return;
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        $invoice->items()->whereNotNull('subscription_id')->with('subscription.tenant')->get()->each(function ($item) use ($invoice): void {
            if (($item->subscription?->status instanceof \BackedEnum ? $item->subscription->status->value : $item->subscription?->status) === 'past_due') {
                $hasOtherPastDue = Invoice::query()->where('id', '!=', $invoice->getKey())->where('status', 'past_due')
                    ->whereHas('items', fn ($items) => $items->where('subscription_id', $item->subscription_id))->exists();
                if ($hasOtherPastDue) return;
                $metadata = $item->subscription->metadata ?? [];
                $billingSuspendedAt = isset($metadata['billing_suspended_at']) ? \Illuminate\Support\Carbon::parse($metadata['billing_suspended_at']) : null;
                unset($metadata['billing_suspended_at']);
                $item->subscription->update(['status' => 'active', 'grace_ends_at' => null, 'metadata' => $metadata ?: null]);
                if ($billingSuspendedAt && $item->subscription->tenant?->suspended_at
                    && $item->subscription->tenant->suspended_at->lessThanOrEqualTo($billingSuspendedAt->copy()->addSeconds(5))) {
                    $this->provisioning->activate($item->subscription->tenant);
                }
            }
        });
        if ($invoice->customer_account_id) CustomerAccountActivity::create([
            'customer_account_id' => $invoice->customer_account_id, 'tenant_id' => $invoice->tenant_id,
            'event' => 'invoice.paid', 'subject_type' => Invoice::class, 'subject_id' => (string) $invoice->getKey(),
            'description' => "Invoice {$invoice->number} was paid.",
        ]);
        if ($invoice->customerAccount && ! $invoice->customerAccount->subscriptions()->where('status', 'past_due')->exists()) {
            $invoice->customerAccount->update(['status' => 'active', 'suspended_at' => null]);
        }
        try {
            app(WorkspacePurchaseService::class)->fulfillForInvoice($invoice);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    public function reopen(Invoice $invoice): void
    {
        if ($invoice->status !== 'void') {
            $invoice->update(['status' => 'open', 'paid_at' => null]);
        }
    }

    public function void(Invoice $invoice): void
    {
        $invoice->update(['status' => 'void', 'voided_at' => now()]);
    }

    private function nextNumber(): string
    {
        $configured = (string) $this->settings->get('payment', 'invoice_prefix', 'INV');
        $prefix = Str::upper((string) preg_replace('/[^A-Za-z0-9-]/', '', $configured));
        $prefix = $prefix !== '' ? $prefix : 'INV';
        $baseline = max(1, (int) $this->settings->get('billing', 'invoice_number_start', 1));
        $sequence = max($baseline, Invoice::query()->lockForUpdate()->count() + 1);
        do {
            $number = $prefix.'-'.str_pad((string) $sequence++, 6, '0', STR_PAD_LEFT);
        } while (Invoice::query()->where('number', $number)->exists());

        return $number;
    }

    private function billingSnapshot(CustomerAccount $account): array
    {
        $profile = $account->billingProfile;

        return [
            'billing_name' => $profile?->billing_name ?: $account->name,
            'billing_email' => $profile?->billing_email ?: $account->billing_email,
            'company_name' => $profile?->company_name ?: $account->legal_name ?: $account->name,
            'address_line_1' => $profile?->address_line_1 ?: $account->address_line_1,
            'address_line_2' => $profile?->address_line_2 ?: $account->address_line_2,
            'city' => $profile?->city ?: $account->city,
            'state' => $profile?->state ?: $account->state,
            'country' => $profile?->country ?: $account->country,
            'postal_code' => $profile?->postal_code ?: $account->postal_code,
            'tax_number' => $profile?->tax_number ?: $account->tax_number,
            'vat_number' => $profile?->vat_number ?: $account->vat_number,
        ];
    }
}
