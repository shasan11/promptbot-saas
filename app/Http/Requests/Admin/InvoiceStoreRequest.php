<?php

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use App\Services\Platform\PlatformSettingsService;
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
            'customer_account_id' => ['required', 'integer', 'exists:customer_accounts,id'],
            'tenant_id' => [app(PlatformSettingsService::class)->get('billing', 'billing_mode_support', 'both') === 'per_service' ? 'required' : 'nullable', 'string', 'exists:tenants,id'],
            'status' => ['required', 'in:draft,open'],
            'currency' => ['required', 'string', 'size:3'],
            'issued_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'tax_total' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'numeric', 'min:0'],
            'items.*.tenant_id' => ['nullable', 'string', 'exists:tenants,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $accountId = (int) $this->input('customer_account_id');
            $tenantIds = collect([$this->input('tenant_id'), ...collect($this->input('items', []))->pluck('tenant_id')->all()])->filter()->unique();
            if ($tenantIds->isNotEmpty() && Tenant::query()->whereIn('id', $tenantIds)->where('customer_account_id', '!=', $accountId)->exists()) {
                $validator->errors()->add('tenant_id', 'Every selected workspace must belong to the selected customer account.');
            }
        });
    }
}
