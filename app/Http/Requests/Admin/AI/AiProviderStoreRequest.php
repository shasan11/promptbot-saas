<?php

namespace App\Http\Requests\Admin\AI;

use App\Enums\AI\AIProviderDriver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AiProviderStoreRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->input('name')).'-'.Str::lower(Str::random(4))]);
        }
    }

    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('ai.providers.manage');
    }

    public function rules(): array
    {
        $driver = $this->input('driver');

        return [
            'driver' => ['required', 'string', 'in:'.implode(',', array_column(AIProviderDriver::cases(), 'value'))],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:150', 'unique:ai_providers,slug'],
            'base_url' => [$driver === AIProviderDriver::Custom->value || $driver === AIProviderDriver::Ollama->value ? 'required' : 'nullable', 'url', 'max:500'],
            'api_key' => [$driver === AIProviderDriver::Ollama->value ? 'nullable' : 'required', 'string', 'max:4096'],
            'organization_id' => ['nullable', 'string', 'max:255'],
            'extra_headers' => ['nullable', 'array'],
            'extra_headers.*' => ['string', 'max:2000'],
            'is_enabled' => ['sometimes', 'boolean'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
            'max_retries' => ['nullable', 'integer', 'min:0', 'max:10'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
