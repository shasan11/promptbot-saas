<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\RendersResourceTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

class WebsiteResourceController extends Controller
{
    use RendersResourceTable;

    public function __invoke(Request $request, ?string $resource = 'overview'): Response
    {
        $map = [
            'overview' => ['Website Pages', 'website_pages', ['title', 'slug', 'status', 'published_at']],
            'branding' => ['Branding', 'website_themes', ['name', 'is_active']],
            'navigation' => ['Navigation', 'website_navigation_items', ['label', 'url', 'sort_order', 'is_active']],
            'pages' => ['Pages', 'website_pages', ['title', 'slug', 'status', 'published_at']],
            'footer' => ['Footer', 'website_footer_links', ['label', 'url', 'group', 'sort_order']],
            'legal' => ['Legal', 'legal_documents', ['title', 'slug', 'published_at']],
            'blog' => ['Blog', 'blog_posts', ['title', 'slug', 'status', 'published_at']],
            'media' => ['Media', 'media', ['disk', 'path', 'mime_type', 'size']],
            'scripts' => ['Scripts', 'website_scripts', ['name', 'placement', 'is_active']],
            'seo' => ['SEO', 'website_pages', ['title', 'slug', 'status', 'published_at']],
            'pricing' => ['Pricing', 'plans', ['name', 'slug', 'monthly_price', 'annual_price', 'is_active']],
        ];

        abort_unless(isset($map[$resource]), 404);
        [$title, $table, $keys] = $map[$resource];

        return $this->tablePage($request, $title, $table, $this->columns($keys), [
            'description' => 'Draft and published website records managed from central data.',
        ]);
    }

    private function columns(array $keys): array
    {
        return collect($keys)->map(fn (string $key) => ['key' => $key, 'label' => str($key)->headline()->toString(), 'searchable' => true])->all();
    }
}
