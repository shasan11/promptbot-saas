<?php

namespace App\Http\Requests\Tenant\Connections;

use App\Enums\Connections\ConnectionType;
use App\Enums\Connections\Environment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ConnectionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('connections.update');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'connection_type' => ['required', new Enum(ConnectionType::class)],
            'environment' => ['required', new Enum(Environment::class)],
            'provider_account_name' => ['nullable', 'string', 'max:255'],
            'usage' => ['nullable', 'array'],
            'usage.*' => ['string', 'max:80'],
            'configuration' => ['nullable', 'array'],
        ];
    }
}
