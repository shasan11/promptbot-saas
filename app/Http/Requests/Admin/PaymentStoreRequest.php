<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('payments.manage');
    }

    public function rules(): array
    {
        return [
            'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
            'invoice_id' => ['nullable', 'uuid', 'exists:invoices,id'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'provider' => ['required', 'in:manual,bank_transfer,stripe,paypal,khalti,esewa'],
            'provider_reference' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:pending,paid,failed'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['nullable', 'date'],
            'failure_reason' => ['nullable', 'required_if:status,failed', 'string', 'max:2000'],
        ];
    }
}
