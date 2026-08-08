<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;

class InvitationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('invitations.create');
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['integer', 'exists:teams,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
