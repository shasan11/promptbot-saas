<?php

namespace App\Http\Requests\Admin\AI;

use App\Enums\AI\AIModelCapability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiModelStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('ai.models.manage');
    }

    protected function prepareForValidation(): void
    {
        // ai_models.input/output_cost_per_million_tokens are NOT NULL with a
        // DB default of 0 — normalize blank input here so we never attempt
        // to write an explicit NULL into a non-nullable column.
        $this->merge([
            'input_cost_per_million_tokens' => $this->input('input_cost_per_million_tokens') ?: 0,
            'output_cost_per_million_tokens' => $this->input('output_cost_per_million_tokens') ?: 0,
        ]);
    }

    public function rules(): array
    {
        $isEmbedding = $this->input('capability') === AIModelCapability::Embedding->value;

        return [
            'ai_provider_id' => ['required', 'uuid', 'exists:ai_providers,id'],
            'model_key' => [
                'required', 'string', 'max:150', 'regex:/^[\w.\-\/:]+$/',
                Rule::unique('ai_models', 'model_key')->where('ai_provider_id', $this->input('ai_provider_id')),
            ],
            'display_name' => ['required', 'string', 'max:150'],
            'capability' => ['required', 'string', 'in:'.implode(',', array_column(AIModelCapability::cases(), 'value'))],
            'context_window' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'embedding_dimensions' => $isEmbedding
                ? ['required', 'integer', 'min:1', 'max:65536']
                : ['nullable', 'prohibited', 'integer', 'min:1', 'max:65536'],
            'input_cost_per_million_tokens' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'output_cost_per_million_tokens' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'supports_streaming' => ['sometimes', 'boolean'],
            'supports_json_mode' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default_for_capability' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'model_key.regex' => 'Model ID may only contain letters, numbers, and . _ - / :',
            'embedding_dimensions.required' => 'Embedding dimensions are required for embedding models.',
            'embedding_dimensions.prohibited' => 'Embedding dimensions only apply to embedding models.',
        ];
    }
}
