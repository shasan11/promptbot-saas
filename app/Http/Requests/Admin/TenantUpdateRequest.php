<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('tenants.update');
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $domainId = $tenant?->domains()->where('is_primary', true)->value('id');

        return [
            'customer_account_id' => ['sometimes', 'required', 'exists:customer_accounts,id'],
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:100'],
            'subdomain' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('domains', 'domain')->ignore($domainId)],
            'plan_id' => ['sometimes', 'nullable', 'exists:plans,id'],
            'status' => ['sometimes', 'in:pending,provisioning,database_creating,database_created,migrating,seeding,active,suspended,failed,deleting,deleted'],
        ];
    }
}
