<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PaymentStoreRequest;
use App\Http\Requests\Admin\PaymentUpdateRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\InvoiceService;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request, PlatformSettingsService $settings): Response
    {
        $currency = strtoupper((string) $settings->get('general', 'default_currency', 'USD'));
        $payments = Payment::query()
            ->with(['tenant:id,company_name', 'invoice:id,number', 'subscription.plan:id,name'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->toString().'%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('provider_reference', 'like', $search)
                        ->orWhereHas('tenant', fn ($tenant) => $tenant->where('company_name', 'like', $search))
                        ->orWhereHas('invoice', fn ($invoice) => $invoice->where('number', 'like', $search));
                });
            })
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->string('provider')->isNotEmpty(), fn ($query) => $query->where('provider', $request->string('provider')))
            ->when($request->string('tenant_id')->isNotEmpty(), fn ($query) => $query->where('tenant_id', $request->string('tenant_id')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Payments/Index', [
            'payments' => $payments,
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
            'filters' => $request->only(['search', 'status', 'provider', 'tenant_id']),
            'stats' => [
                'currency' => $currency,
                'total' => Payment::query()->count(),
                'paid' => Payment::query()->where('currency', $currency)->whereIn('status', ['paid', 'partially_refunded', 'refunded'])->sum('amount'),
                'pending' => Payment::query()->where('status', 'pending')->count(),
                'refunded' => Payment::query()->where('currency', $currency)->sum('refunded_amount'),
            ],
        ]);
    }

    public function create(PlatformSettingsService $settings): Response
    {
        return Inertia::render('Admin/Payments/Create', $this->formData($settings));
    }

    public function store(
        PaymentStoreRequest $request,
        InvoiceService $invoices,
        AuditLogService $auditLog
    ): RedirectResponse {
        $data = $this->normalize($request->validated());
        $this->validateRelationships($data);
        $data['created_by'] = $request->user('central')?->id;

        $payment = Payment::create($data);
        $this->syncInvoiceSettlement($payment->invoice_id, $invoices);

        $auditLog->record('payment.created', $payment, [
            'tenant_id' => $payment->tenant_id,
            'new_values' => $payment->only(['invoice_id', 'subscription_id', 'provider', 'provider_reference', 'status', 'amount', 'currency']),
        ]);

        return redirect()->route('superadmin.billing.payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function show(Payment $payment): Response
    {
        return Inertia::render('Admin/Payments/Show', [
            'payment' => $payment->load([
                'tenant:id,company_name,public_uuid',
                'invoice:id,number,status,total,currency',
                'subscription.plan:id,name',
                'creator:id,name,email',
                'refunds.creator:id,name,email',
            ]),
            'refundableAmount' => $payment->refundableAmount(),
        ]);
    }

    public function edit(Payment $payment, PlatformSettingsService $settings): Response|RedirectResponse
    {
        if ((float) $payment->refunded_amount > 0) {
            return redirect()->route('superadmin.billing.payments.show', $payment)
                ->with('error', 'A payment with refunds cannot be edited. Record an adjusting payment instead.');
        }

        return Inertia::render('Admin/Payments/Create', [
            ...$this->formData($settings),
            'payment' => $payment,
        ]);
    }

    public function update(
        PaymentUpdateRequest $request,
        Payment $payment,
        InvoiceService $invoices,
        AuditLogService $auditLog
    ): RedirectResponse {
        if ((float) $payment->refunded_amount > 0) {
            return back()->with('error', 'A payment with refunds cannot be edited. Record an adjusting payment instead.');
        }

        $oldInvoiceId = $payment->invoice_id;
        $data = $this->normalize($request->validated(), $payment);
        $this->validateRelationships([...$payment->toArray(), ...$data]);
        $oldValues = $payment->only(array_keys($data));
        $payment->update($data);

        $invoiceIds = array_unique(array_filter([$oldInvoiceId, $payment->fresh()->invoice_id]));
        foreach ($invoiceIds as $invoiceId) {
            $this->syncInvoiceSettlement($invoiceId, $invoices);
        }

        $auditLog->record('payment.updated', $payment, [
            'tenant_id' => $payment->tenant_id,
            'old_values' => $oldValues,
            'new_values' => $data,
        ]);

        return redirect()->route('superadmin.billing.payments.show', $payment)->with('status', 'Payment updated.');
    }

    public function refund(
        Request $request,
        Payment $payment,
        InvoiceService $invoices,
        AuditLogService $auditLog
    ): RedirectResponse {
        abort_unless($request->user('central')?->can('payments.manage'), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:2000'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
        ]);

        if (! in_array($payment->status, ['paid', 'partially_refunded'], true)) {
            throw ValidationException::withMessages([
                'amount' => 'Only paid payments can be refunded.',
            ]);
        }

        if ((float) $validated['amount'] > $payment->refundableAmount()) {
            throw ValidationException::withMessages([
                'amount' => 'Refund amount exceeds the remaining refundable balance.',
            ]);
        }

        $refund = DB::transaction(function () use ($request, $payment, $validated) {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $remaining = $lockedPayment->refundableAmount();

            if ((float) $validated['amount'] > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Refund amount exceeds the remaining refundable balance.',
                ]);
            }

            $refund = $lockedPayment->refunds()->create([
                'amount' => $validated['amount'],
                'status' => 'completed',
                'reason' => $validated['reason'],
                'provider_reference' => $validated['provider_reference'] ?? null,
                'processed_at' => now(),
                'created_by' => $request->user('central')?->id,
            ]);

            $refundedAmount = round((float) $lockedPayment->refunded_amount + (float) $validated['amount'], 2);
            $lockedPayment->update([
                'refunded_amount' => $refundedAmount,
                'status' => $refundedAmount >= (float) $lockedPayment->amount ? 'refunded' : 'partially_refunded',
            ]);

            return $refund;
        });

        $this->syncInvoiceSettlement($payment->invoice_id, $invoices);

        $auditLog->record('payment.refunded', $payment, [
            'tenant_id' => $payment->tenant_id,
            'reason' => $validated['reason'],
            'new_values' => ['refund_id' => $refund->id, 'amount' => $validated['amount']],
            'severity' => 'warning',
        ]);

        return back()->with('status', 'Refund recorded.');
    }

    private function formData(PlatformSettingsService $settings): array
    {
        return [
            'tenants' => Tenant::query()->orderBy('company_name')->get(['id', 'company_name']),
            'invoices' => Invoice::query()->with('tenant:id,company_name')->latest('issued_on')->get(['id', 'tenant_id', 'number', 'status', 'total', 'currency']),
            'subscriptions' => Subscription::query()->with(['tenant:id,company_name', 'plan:id,name'])->latest()->get(['id', 'tenant_id', 'plan_id', 'status']),
            'defaults' => [
                'provider' => $settings->get('payment', 'default_gateway', 'manual'),
                'currency' => strtoupper((string) $settings->get('general', 'default_currency', 'USD')),
            ],
        ];
    }

    private function normalize(array $data, ?Payment $payment = null): array
    {
        if (array_key_exists('currency', $data)) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $status = $data['status'] ?? $payment?->status;

        if ($status === 'paid') {
            $data['paid_at'] = $data['paid_at'] ?? $payment?->paid_at ?? now();
            $data['failed_at'] = null;
            $data['failure_reason'] = null;
        } elseif ($status === 'failed') {
            $data['failed_at'] = $payment?->failed_at ?? now();
            $data['paid_at'] = null;
        } elseif ($status === 'pending') {
            $data['paid_at'] = null;
            $data['failed_at'] = null;
            $data['failure_reason'] = null;
        }

        return $data;
    }

    private function validateRelationships(array $data): void
    {
        if (! empty($data['invoice_id'])) {
            $validInvoice = Invoice::query()
                ->whereKey($data['invoice_id'])
                ->where('tenant_id', $data['tenant_id'])
                ->exists();

            if (! $validInvoice) {
                throw ValidationException::withMessages([
                    'invoice_id' => 'The selected invoice does not belong to the selected tenant.',
                ]);
            }
        }

        if (! empty($data['subscription_id'])) {
            $validSubscription = Subscription::query()
                ->whereKey($data['subscription_id'])
                ->where('tenant_id', $data['tenant_id'])
                ->exists();

            if (! $validSubscription) {
                throw ValidationException::withMessages([
                    'subscription_id' => 'The selected subscription does not belong to the selected tenant.',
                ]);
            }
        }
    }

    private function syncInvoiceSettlement(?string $invoiceId, InvoiceService $invoices): void
    {
        if (! $invoiceId) {
            return;
        }

        $invoice = Invoice::query()->find($invoiceId);
        if (! $invoice || $invoice->status === 'void') {
            return;
        }

        $netPaid = Payment::query()
            ->where('invoice_id', $invoiceId)
            ->whereIn('status', ['paid', 'partially_refunded', 'refunded'])
            ->get(['amount', 'refunded_amount'])
            ->sum(fn (Payment $payment) => (float) $payment->amount - (float) $payment->refunded_amount);

        if ($netPaid + 0.0001 >= (float) $invoice->total) {
            if ($invoice->status !== 'paid') {
                $invoices->markPaid($invoice);
            }
        } elseif ($invoice->status === 'paid') {
            $invoices->reopen($invoice);
        }
    }
}
