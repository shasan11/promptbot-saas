<?php

namespace App\Http\Requests\Admin\AI;

use App\Enums\AI\AIModelCapability;
use Illuminate\Foundation\Http\FormRequest;

class AiModelUpdateRequest extends FormRequest
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
        if ($this->has('input_cost_per_million_tokens')) {
            $this->merge(['input_cost_per_million_tokens' => $this->input('input_cost_per_million_tokens') ?: 0]);
        }
        if ($this->has('output_cost_per_million_tokens')) {
            $this->merge(['output_cost_per_million_tokens' => $this->input('output_cost_per_million_tokens') ?: 0]);
        }
    }

    public function rules(): array
    {
        $isEmbedding = $this->route('model')?->capability === AIModelCapability::Embedding;

        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:150'],
            'context_window' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'max_output_tokens' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100000000'],
            'embedding_dimensions' => $isEmbedding
                ? ['sometimes', 'required', 'integer', 'min:1', 'max:65536']
                : ['sometimes', 'nullable', 'prohibited', 'integer', 'min:1', 'max:65536'],
            'input_cost_per_million_tokens' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'output_cost_per_million_tokens' => ['sometimes', 'required', 'numeric', 'min:0', 'max:1000000'],
            'supports_streaming' => ['sometimes', 'boolean'],
            'supports_json_mode' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default_for_capability' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'embedding_dimensions.required' => 'Embedding dimensions are required for embedding models.',
            'embedding_dimensions.prohibited' => 'Embedding dimensions only apply to embedding models.',
        ];
    }
}
