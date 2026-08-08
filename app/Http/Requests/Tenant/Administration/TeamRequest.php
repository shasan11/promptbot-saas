<?php

namespace App\Http\Requests\Tenant\Administration;

use Illuminate\Foundation\Http\FormRequest;

class TeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = $this->isMethod('post') ? 'create' : 'update';

        return (bool) $this->user('tenant')?->can("teams.{$ability}");
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'lead_user_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', 'in:active,archived'],
        ];
    }
}
