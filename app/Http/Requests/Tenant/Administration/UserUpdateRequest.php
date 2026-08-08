<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('users.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'account_expires_at' => ['nullable', 'date'],
        ];
    }
}
