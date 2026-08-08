<?php

namespace App\Http\Requests\Tenant\Connections;

use Illuminate\Foundation\Http\FormRequest;

class ConnectionActionExecuteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('connections.actions.execute');
    }

    public function rules(): array
    {
        return [
            'input' => ['nullable', 'array'],
            'agent_key' => ['nullable', 'string', 'max:120'],
            'workflow_key' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
