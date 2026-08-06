<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsitePageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('central')?->can('website.manage');
    }

    public function rules(): array
    {
        $pageId = $this->route('page')?->id;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('website_pages', 'slug')->ignore($pageId)],
            'status' => ['required', 'in:draft,published'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
