<?php

namespace App\Http\Requests\Tenant\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can($this->isMethod('post') ? 'customers.create' : 'customers.update');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:120', 'required_without_all:last_name,display_name,email,phone'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'secondary_email' => ['nullable', 'email:rfc', 'max:255', 'different:email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'secondary_phone' => ['nullable', 'string', 'max:50', 'different:phone'],
            'country' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'timezone:all'],
            'preferred_language' => ['nullable', 'string', 'max:12'],
            'status' => ['required', Rule::in(['active', 'inactive', 'blocked', 'vip'])],
            'source' => ['nullable', 'string', 'max:64'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'contact_points' => ['nullable', 'array', 'max:20'],
            'contact_points.*.type' => ['required', Rule::in(['email', 'phone', 'whatsapp', 'social'])],
            'contact_points.*.label' => ['nullable', 'string', 'max:64'],
            'contact_points.*.value' => ['required', 'string', 'max:255'],
            'contact_points.*.is_primary' => ['boolean'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'custom_fields' => ['nullable', 'array'],
        ];
    }
}
