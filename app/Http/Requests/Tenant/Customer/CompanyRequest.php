<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user('tenant')?->can($this->isMethod('post') ? 'companies.create' : 'companies.update'); }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'], 'domain' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:120'], 'website' => ['nullable', 'url:http,https', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'], 'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2'], 'postal_code' => ['nullable', 'string', 'max:32'],
            'account_owner_id' => ['nullable', 'exists:users,id'], 'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'notes' => ['nullable', 'string', 'max:10000'], 'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'], 'custom_fields' => ['nullable', 'array'],
        ];
    }
}
