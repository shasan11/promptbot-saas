<?php

namespace App\Http\Requests\Tenant\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'knowledge_base' => ['required', 'string', 'uuid'],
            'collection_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'content' => ['required', 'string', 'min:20', 'max:200000'],
            'language' => ['nullable', 'string', Rule::in(array_keys((array) config('knowledge.languages')))],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'content.min' => 'Manual text needs at least a short paragraph before it can be useful to retrieval.',
            'effective_until.after' => 'The end of the effective window must come after its start.',
        ];
    }
}
