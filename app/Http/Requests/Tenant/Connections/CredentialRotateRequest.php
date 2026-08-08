<?php

namespace App\Http\Requests\Tenant\Connections;

use App\Enums\Connections\AuthenticationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class CredentialRotateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('tenant')?->can('connections.credentials.manage');
    }

    public function rules(): array
    {
        return [
            'auth_type' => ['nullable', new Enum(AuthenticationType::class)],
            'credential' => ['required', 'array', 'min:1'],
            'credential.*' => ['nullable'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date'],
            'refresh_expires_at' => ['nullable', 'date', 'after_or_equal:expires_at'],
        ];
    }
}
