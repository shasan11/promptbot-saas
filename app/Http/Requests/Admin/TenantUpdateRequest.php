<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TenantUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('central')?->can('tenants.update') === true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'plan_id' => ['sometimes', 'nullable', 'exists:plans,id'],
            'status' => ['sometimes', 'in:pending,provisioning,database_creating,database_created,migrating,seeding,active,suspended,failed,deleting,deleted'],
        ];
    }
}
