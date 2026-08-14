<?php

namespace App\Http\Requests\Admin\AI;

use Illuminate\Foundation\Http\FormRequest;

class AiModelUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('ai.models.manage');
    }

    public function rules(): array
    {
        return [
            'display_name' => ['sometimes', 'required', 'string', 'max:150'],
            'context_window' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'max_output_tokens' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'embedding_dimensions' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'input_cost_per_million_tokens' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'output_cost_per_million_tokens' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'supports_streaming' => ['sometimes', 'boolean'],
            'supports_json_mode' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default_for_capability' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
