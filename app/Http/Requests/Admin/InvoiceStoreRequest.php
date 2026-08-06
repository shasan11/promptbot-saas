<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('invoices.manage');
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'status' => ['required', 'in:draft,open'],
            'currency' => ['required', 'string', 'size:3'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}
