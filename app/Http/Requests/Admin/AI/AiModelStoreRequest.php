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

    public function rules(): array
    {
        return [
            'ai_provider_id' => ['required', 'uuid', 'exists:ai_providers,id'],
            'model_key' => [
                'required', 'string', 'max:150',
                Rule::unique('ai_models', 'model_key')->where('ai_provider_id', $this->input('ai_provider_id')),
            ],
            'display_name' => ['required', 'string', 'max:150'],
            'capability' => ['required', 'string', 'in:'.implode(',', array_column(AIModelCapability::cases(), 'value'))],
            'context_window' => ['nullable', 'integer', 'min:1'],
            'max_output_tokens' => ['nullable', 'integer', 'min:1'],
            'embedding_dimensions' => ['nullable', 'integer', 'min:1'],
            'input_cost_per_million_tokens' => ['nullable', 'numeric', 'min:0'],
            'output_cost_per_million_tokens' => ['nullable', 'numeric', 'min:0'],
            'supports_streaming' => ['sometimes', 'boolean'],
            'supports_json_mode' => ['sometimes', 'boolean'],
            'is_enabled' => ['sometimes', 'boolean'],
            'is_default_for_capability' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
