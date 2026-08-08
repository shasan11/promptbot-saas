<?php

namespace App\Http\Requests\Tenant\Knowledge;

use App\Services\Knowledge\DocumentUploadService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * First-pass upload validation.
 *
 * These rules cover shape and size only. The authoritative content check —
 * sniffing the real MIME type and confirming it matches the extension — happens
 * in DocumentUploadService, because Laravel's `mimes:` rule can be satisfied by
 * a file whose extension merely looks right.
 */
class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('knowledge.uploads.max_file_size_kb');
        $maxFiles = (int) config('knowledge.uploads.max_files_per_request');

        return [
            'knowledge_base' => ['required', 'string', 'uuid'],
            'collection_id' => ['nullable', 'integer'],
            'files' => ['required', 'array', "max:{$maxFiles}"],
            'files.*' => ['required', 'file', "max:{$maxKb}"],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
            'on_duplicate' => ['nullable', Rule::in([
                DocumentUploadService::ON_DUPLICATE_SKIP,
                DocumentUploadService::ON_DUPLICATE_REPLACE,
                DocumentUploadService::ON_DUPLICATE_ADD,
            ])],
        ];
    }

    public function messages(): array
    {
        return [
            'files.max' => 'You can upload at most :max files at once. Add the rest in a second batch.',
            'files.*.max' => 'Each file must be smaller than '.round((int) config('knowledge.uploads.max_file_size_kb') / 1024).' MB.',
        ];
    }
}
