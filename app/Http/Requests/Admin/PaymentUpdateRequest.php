<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PaymentUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('payments.manage');
    }

    public function rules(): array
    {
        return [
            'customer_account_id' => ['sometimes', 'required', 'integer', 'exists:customer_accounts,id'],
            'tenant_id' => ['sometimes', 'nullable', 'string', 'exists:tenants,id'],
            'invoice_id' => ['sometimes', 'nullable', 'uuid', 'exists:invoices,id'],
            'subscription_id' => ['sometimes', 'nullable', 'integer', 'exists:subscriptions,id'],
            'provider' => ['sometimes', 'required', 'in:manual,bank_transfer,stripe,paypal,khalti,esewa'],
            'provider_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:pending,paid,failed'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'paid_at' => ['sometimes', 'nullable', 'date'],
            'failure_reason' => ['nullable', 'required_if:status,failed', 'string', 'max:2000'],
        ];
    }
}
