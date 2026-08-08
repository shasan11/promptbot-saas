<?php

namespace App\Http\Requests\Tenant\Knowledge;

use App\Enums\Knowledge\FaqStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'knowledge_base' => ['required_without:knowledge_base_id', 'nullable', 'string', 'uuid'],
            'collection_id' => ['nullable', 'integer'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:120'],
            'language' => ['nullable', 'string', Rule::in(array_keys((array) config('knowledge.languages')))],
            'status' => ['nullable', Rule::enum(FaqStatus::class)],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_until.after' => 'The end of the effective window must come after its start.',
        ];
    }
}
