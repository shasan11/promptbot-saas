<?php

namespace App\Http\Requests\Tenant\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'language' => ['nullable', 'string', Rule::in(array_keys((array) config('knowledge.languages')))],
            'allow_ai_access' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ];
    }
}
