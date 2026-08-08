<?php

namespace App\Http\Requests\Tenant\Knowledge;

use App\Enums\Knowledge\SourceType;
use App\Enums\Knowledge\SyncFrequency;
use App\Services\Knowledge\Crawler\UrlSafetyGuard;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebsiteSourceRequest extends FormRequest
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
            'name' => ['nullable', 'string', 'max:150'],
            'url' => ['required', 'url', 'max:2048'],
            'source_type' => ['required', Rule::in([
                SourceType::Website->value,
                SourceType::WebsiteCrawl->value,
                SourceType::Sitemap->value,
            ])],
            'sync_frequency' => ['nullable', Rule::enum(SyncFrequency::class)],

            'crawl.max_pages' => ['nullable', 'integer', 'min:1', 'max:'.config('knowledge.crawler.max_pages_limit')],
            'crawl.max_depth' => ['nullable', 'integer', 'min:0', 'max:'.config('knowledge.crawler.max_depth_limit')],
            'crawl.allowed_paths' => ['nullable', 'array', 'max:50'],
            'crawl.allowed_paths.*' => ['string', 'max:255'],
            'crawl.excluded_paths' => ['nullable', 'array', 'max:50'],
            'crawl.excluded_paths.*' => ['string', 'max:255'],
            'crawl.include_subdomains' => ['nullable', 'boolean'],
            'crawl.follow_external' => ['nullable', 'boolean'],
            'crawl.respect_robots' => ['nullable', 'boolean'],
            'crawl.use_sitemap' => ['nullable', 'boolean'],
            'crawl.sitemap_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /**
     * Rejects unsafe targets at the edge.
     *
     * The crawler re-validates every URL immediately before fetching it — that
     * is the real control, since DNS can change in between. Checking here as
     * well means the user gets an immediate, comprehensible validation error on
     * the form instead of a source that saves cleanly and then fails silently on
     * its first run.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $guard = app(UrlSafetyGuard::class);

                foreach (['url', 'crawl.sitemap_url'] as $field) {
                    $value = $this->input($field);

                    if (! $value || $validator->errors()->has($field)) {
                        continue;
                    }

                    if (! $guard->isSafe($value)) {
                        $validator->errors()->add(
                            $field,
                            'This address cannot be crawled. Enter a public website URL.'
                        );
                    }
                }
            },
        ];
    }
}
