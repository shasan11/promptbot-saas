<?php

namespace App\Http\Requests\Tenant\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConnectionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('connections.create');
    }

    public function rules(): array
    {
        return [
            'connection_integration_id' => ['required', 'exists:connection_integrations,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'connection_type' => ['required', new Enum(ConnectionType::class)],
            'auth_type' => ['required', new Enum(AuthenticationType::class)],
            'environment' => ['required', new Enum(Environment::class)],
            'provider_account_name' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', 'array'],
            'usage.*' => ['string', 'max:80'],
            'configuration' => ['nullable', 'array'],
            'credential' => ['nullable', 'array'],
        ];
    }
}
