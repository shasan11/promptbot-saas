<?php

namespace App\Http\Requests\Tenant\Knowledge;

use App\Enums\Knowledge\ChunkingStrategy;
use App\Enums\Knowledge\KnowledgeVisibility;
use App\Enums\Knowledge\RetrievalMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation lives in the policy, invoked by the controller — doing
        // it here as well would split the rule across two places.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'default_language' => ['required', 'string', Rule::in(array_keys((array) config('knowledge.languages')))],
            'supported_languages' => ['nullable', 'array', 'max:20'],
            'supported_languages.*' => ['string', Rule::in(array_keys((array) config('knowledge.languages')))],
            'visibility' => ['required', Rule::enum(KnowledgeVisibility::class)],
            'icon' => ['nullable', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'max:32'],

            'embedding_provider' => ['sometimes', Rule::in(array_keys((array) config('knowledge.embeddings.providers')))],

            'chunking_strategy' => ['sometimes', Rule::enum(ChunkingStrategy::class)],
            // Bounded to the platform limits so a tenant cannot configure a
            // chunk size that produces one giant embedding per document.
            'chunk_size' => [
                'sometimes', 'integer',
                'min:'.config('knowledge.chunking.min_chunk_size'),
                'max:'.config('knowledge.chunking.max_chunk_size'),
            ],
            'chunk_overlap' => ['sometimes', 'integer', 'min:0', 'lt:chunk_size'],

            'retrieval_mode' => ['sometimes', Rule::enum(RetrievalMode::class)],
            'top_k' => ['sometimes', 'integer', 'min:1', 'max:'.config('knowledge.retrieval.max_top_k')],
            'candidate_pool' => ['sometimes', 'integer', 'min:1', 'max:'.config('knowledge.retrieval.max_candidate_pool'), 'gte:top_k'],
            'similarity_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'reranking_enabled' => ['sometimes', 'boolean'],
            'max_context_tokens' => ['sometimes', 'integer', 'min:500', 'max:'.config('knowledge.retrieval.max_context_tokens')],
            'allow_cross_source_retrieval' => ['sometimes', 'boolean'],
            'prefer_recent_content' => ['sometimes', 'boolean'],
            'require_citations' => ['sometimes', 'boolean'],
            'exclude_expired_content' => ['sometimes', 'boolean'],
            'review_every_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    public function messages(): array
    {
        return [
            'chunk_overlap.lt' => 'The chunk overlap must be smaller than the chunk size, or chunking would never advance.',
            'candidate_pool.gte' => 'The candidate pool must be at least as large as the number of results returned.',
        ];
    }
}
