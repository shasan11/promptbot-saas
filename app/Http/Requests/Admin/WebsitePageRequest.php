<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsitePageRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'page_type' => $this->input('page_type', $this->route('page')?->page_type ?? 'standard'),
            'template' => $this->input('template', $this->route('page')?->template ?? 'default'),
            'robots_index' => $this->has('robots_index') ? $this->boolean('robots_index') : true,
            'robots_follow' => $this->has('robots_follow') ? $this->boolean('robots_follow') : true,
        ]);
    }

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
            'status' => ['required', 'in:draft,scheduled,published,archived'],
            'page_type' => ['required', 'in:standard,home,pricing,features,integrations,about,contact,legal,custom'],
            'template' => ['required', 'string', 'max:100'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'robots_index' => ['boolean'], 'robots_follow' => ['boolean'],
            'og_title' => ['nullable', 'string', 'max:255'], 'og_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'twitter_title' => ['nullable', 'string', 'max:255'], 'twitter_description' => ['nullable', 'string', 'max:500'],
            'twitter_image' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'schema_json' => ['nullable', 'array'],
            'scheduled_at' => ['nullable', 'required_if:status,scheduled', 'date', 'after:now'],
            'create_slug_redirect' => ['boolean'],
        ];
    }

    private function safePublicUrl(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') return;
            $value = trim((string) $value);
            if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) return;
            if (filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) return;
            $fail("The {$attribute} must be a relative path or an HTTP(S) URL.");
        };
    }
}
