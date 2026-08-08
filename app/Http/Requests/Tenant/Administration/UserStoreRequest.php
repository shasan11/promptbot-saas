<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('users.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:10'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'timezone' => ['nullable', 'timezone'],
            'locale' => ['nullable', 'string', 'max:10'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
        ];
    }
}
