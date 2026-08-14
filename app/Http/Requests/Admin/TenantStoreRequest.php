<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TenantStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('tenants.create');
    }

    public function rules(): array
    {
        return [
            // Nullable for legacy/API callers; Tenant's compatibility resolver
            // creates and links a deterministic account when omitted.
            'customer_account_id' => ['nullable', 'exists:customer_accounts,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'alpha_dash:ascii', 'max:60', 'unique:tenants,slug'],
            'subdomain' => ['nullable', 'string', 'max:255', 'unique:domains,domain'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email:rfc'],
            'owner_password' => ['required', 'string', 'min:10'],
            'plan_id' => ['nullable', 'exists:plans,id'],
            'provisioning_mode' => ['nullable', 'in:manual,cpanel,mysql'],
            'execution_mode' => ['nullable', 'in:immediate,queue'],
            'database_host' => ['nullable', 'required_if:provisioning_mode,manual', 'string', 'max:255'],
            'database_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database_name' => ['nullable', 'required_if:provisioning_mode,manual', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'database_username' => ['nullable', 'required_if:provisioning_mode,manual', 'string', 'max:255'],
            'database_password' => ['nullable', 'string'],
        ];
    }
}
