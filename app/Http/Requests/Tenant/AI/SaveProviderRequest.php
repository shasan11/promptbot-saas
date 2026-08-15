<?php

namespace App\Http\Requests\Tenant\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('tenant')?->can('ai.providers.manage') ?? false;
    }

    public function rules(): array
    {
        $providers = array_keys((array) config('ai.providers'));
        return [
            'name' => ['required', 'string', 'max:100'],
            'provider' => ['required', Rule::in($providers)],
            'enabled' => ['required', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'base_url' => ['nullable', 'url:http,https', 'max:2048', Rule::requiredIf(in_array($this->input('provider'), ['openai_compatible', 'ollama'], true))],
            'organization' => ['nullable', 'string', 'max:255'],
            'default_chat_model' => ['required', 'string', 'max:150'],
            'default_fast_model' => ['nullable', 'string', 'max:150'],
            'default_reasoning_model' => ['nullable', 'string', 'max:150'],
            'default_embedding_model' => ['nullable', 'string', 'max:150'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:1.5'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_tokens' => ['nullable', 'integer', 'min:64', 'max:8192'],
            'pricing_currency' => ['nullable', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'input_cost_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'output_cost_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cached_input_cost_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'reasoning_cost_per_million' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ];
    }
}
