<?php

namespace App\Http\Requests\Tenant\Knowledge;

use App\Enums\Knowledge\RetrievalMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetrievalTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:1000'],
            'knowledge_bases' => ['nullable', 'array', 'max:20'],
            'knowledge_bases.*' => ['string', 'uuid'],
            'collection_ids' => ['nullable', 'array', 'max:50'],
            'collection_ids.*' => ['integer'],
            'source_ids' => ['nullable', 'array', 'max:50'],
            'source_ids.*' => ['integer'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'language' => ['nullable', 'string', 'max:12'],
            'mode' => ['nullable', Rule::enum(RetrievalMode::class)],
            'top_k' => ['nullable', 'integer', 'min:1', 'max:'.config('knowledge.retrieval.max_top_k')],
            'similarity_threshold' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'rerank' => ['nullable', 'boolean'],
            // Whether the caller *may* see debug output is decided by the
            // policy, not by this flag — the flag only expresses intent.
            'debug' => ['nullable', 'boolean'],
            'generate_answer' => ['nullable', 'boolean'],
        ];
    }
}
