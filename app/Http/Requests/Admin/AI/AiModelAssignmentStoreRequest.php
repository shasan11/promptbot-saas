<?php

namespace App\Http\Requests\Admin\AI;

use App\Enums\AI\AIPurpose;
use Illuminate\Foundation\Http\FormRequest;

class AiModelAssignmentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('ai.models.manage');
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', 'in:'.implode(',', array_column(AIPurpose::cases(), 'value'))],
            'ai_model_id' => ['required', 'uuid', 'exists:ai_models,id'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }
}
