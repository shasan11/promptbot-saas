<?php

namespace App\Services\Platform;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentWebhookService
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
        private readonly InvoiceService $invoices,
        private readonly PortalNotificationService $notifications,
    ) {}

    public function verify(string $provider, string $rawPayload, string $signature): bool
    {
        $configuredProvider = (string) $this->settings->get('payment', 'default_gateway', 'manual');
        $secret = (string) $this->settings->get('payment', 'gateway_webhook_secret', '');
        if ($provider !== $configuredProvider || $provider === 'manual' || $secret === '') return false;
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals(hash_hmac('sha256', $rawPayload, $secret), strtolower(trim($provided)));
    }

    public function handle(string $provider, array $payload, string $rawPayload): PaymentWebhookEvent
    {
        $eventId = (string) ($payload['id'] ?? 'missing');
        return Cache::lock('payment-webhook:'.hash('sha256', $provider.'|'.$eventId), 120)->block(5,
            fn () => $this->process($provider, $payload, $rawPayload));
    }

    private function process(string $provider, array $payload, string $rawPayload): PaymentWebhookEvent
    {
        $data = validator($payload, [
            'id' => ['required', 'string', 'max:255'], 'type' => ['required', 'string', 'max:100'],
            'invoice_number' => ['required', 'string', 'max:100'], 'payment_reference' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:paid,failed,pending'], 'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'], 'failure_reason' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return Cache::lock('payment-reference:'.hash('sha256', $provider.'|'.$data['payment_reference']), 120)->block(5,
            fn () => $this->processLocked($provider, $data, $rawPayload));
    }

    private function processLocked(string $provider, array $data, string $rawPayload): PaymentWebhookEvent
    {
        $existing = PaymentWebhookEvent::where('provider', $provider)->where('provider_event_id', $data['id'])->first();
        if ($existing) {
            if (! hash_equals($existing->payload_hash, hash('sha256', $rawPayload))) {
                throw ValidationException::withMessages(['id' => 'This provider event ID was already used with a different payload.']);
            }
            return $existing;
        }

        $invoice = Invoice::where('number', $data['invoice_number'])->firstOrFail();
        if (strtoupper($data['currency']) !== strtoupper($invoice->currency) || (float) $data['amount'] > (float) $invoice->total + 0.0001) {
            throw ValidationException::withMessages(['amount' => 'Webhook amount or currency does not match the invoice.']);
        }

        try {
            [$event, $payment] = DB::transaction(function () use ($provider, $data, $rawPayload, $invoice): array {
                $event = PaymentWebhookEvent::where('provider', $provider)->where('provider_event_id', $data['id'])->lockForUpdate()->first();
                if ($event) return [$event, $event->payment_id ? Payment::find($event->payment_id) : null];
                $event = PaymentWebhookEvent::create([
                    'provider' => $provider, 'provider_event_id' => $data['id'], 'type' => $data['type'],
                    'status' => 'processing', 'invoice_id' => $invoice->getKey(), 'payload_hash' => hash('sha256', $rawPayload),
                ]);
                $payment = Payment::updateOrCreate(
                    ['provider' => $provider, 'provider_reference' => $data['payment_reference']],
                    ['customer_account_id' => $invoice->customer_account_id, 'tenant_id' => $invoice->tenant_id,
                        'invoice_id' => $invoice->getKey(), 'status' => $data['status'], 'amount' => $data['amount'],
                        'currency' => strtoupper($data['currency']), 'paid_at' => $data['status'] === 'paid' ? now() : null,
                        'failed_at' => $data['status'] === 'failed' ? now() : null, 'failure_reason' => $data['status'] === 'failed' ? ($data['failure_reason'] ?? 'Provider declined payment.') : null],
                );
                $event->update(['payment_id' => $payment->getKey(), 'status' => 'processed', 'processed_at' => now()]);
                return [$event, $payment];
            });

            if ($payment?->status === 'paid') {
                $this->notifications->capability($invoice->customer_account_id, 'can_manage_billing', 'billing.payment_received', 'Payment received', "{$payment->currency} ".number_format((float) $payment->amount, 2).' was received.', route('portal.billing.payments', absolute: false), ['payment_id' => $payment->getKey(), 'payment_amount' => $payment->currency.' '.number_format((float) $payment->amount, 2)], $payment->tenant_id);
                $this->settleIfCovered($invoice);
            }
            if ($payment?->status === 'failed') $this->notifications->capability($invoice->customer_account_id, 'can_manage_billing', 'billing.payment_failed', 'Payment failed', "{$payment->currency} ".number_format((float) $payment->amount, 2).' could not be processed.', route('portal.billing.payments', absolute: false), ['payment_id' => $payment->getKey(), 'payment_amount' => $payment->currency.' '.number_format((float) $payment->amount, 2)], $payment->tenant_id);

            return $event->refresh();
        } catch (Throwable $exception) {
            PaymentWebhookEvent::where('provider', $provider)->where('provider_event_id', $data['id'])
                ->update(['status' => 'failed', 'failure_reason' => str($exception->getMessage())->limit(1000), 'processed_at' => now()]);
            throw $exception;
        }
    }

    private function settleIfCovered(Invoice $invoice): void
    {
        $net = $invoice->payments()->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->get()
            ->sum(fn (Payment $payment) => (float) $payment->amount - (float) $payment->refunded_amount);
        if ($net + 0.0001 >= (float) $invoice->total) $this->invoices->markPaid($invoice->refresh());
    }
}
