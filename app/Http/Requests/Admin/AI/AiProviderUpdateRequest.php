<?php

namespace App\Http\Requests\Admin\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('ai.providers.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'slug' => ['sometimes', 'required', 'alpha_dash', 'max:150', Rule::unique('ai_providers', 'slug')->ignore($this->route('provider'))],
            'base_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            // Blank means "keep the existing key" — handled in the controller, never required here.
            'api_key' => ['sometimes', 'nullable', 'string', 'max:4096'],
            'organization_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'extra_headers' => ['sometimes', 'nullable', 'array'],
            'extra_headers.*' => ['string', 'max:2000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'timeout_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:600'],
            'max_retries' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
