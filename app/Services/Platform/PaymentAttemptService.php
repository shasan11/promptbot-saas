<?php

namespace App\Services\Platform;

use App\Models\CustomerAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;

class PaymentAttemptService
{
    public function __construct(private readonly PlatformSettingsService $settings) {}

    public function forInvoice(CustomerAccount $account, Invoice $invoice, string $key, ?Payment $retry = null): PaymentAttempt
    {
        $idempotencyKey = hash('sha256', $account->getKey().'|'.$invoice->getKey().'|'.$key);

        return DB::transaction(function () use ($account, $invoice, $retry, $idempotencyKey): PaymentAttempt {
            $existing = PaymentAttempt::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $provider = (string) $this->settings->get('payment', 'default_gateway', 'manual');
            return PaymentAttempt::create([
                'customer_account_id' => $account->getKey(), 'invoice_id' => $invoice->getKey(), 'payment_id' => $retry?->getKey(),
                'provider' => $provider, 'status' => 'requires_action', 'idempotency_key' => $idempotencyKey,
                'amount' => max(0, (float) $invoice->total - $this->netPaid($invoice)), 'currency' => $invoice->currency,
                'metadata' => ['instruction' => $provider === 'manual'
                    ? 'Contact billing support and quote the invoice number to complete payment.'
                    : 'Continue in the configured payment provider checkout.'],
            ]);
        });
    }

    private function netPaid(Invoice $invoice): float
    {
        return (float) $invoice->payments()->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->get()
            ->sum(fn (Payment $payment) => (float) $payment->amount - (float) $payment->refunded_amount);
    }
}
