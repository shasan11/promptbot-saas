<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\Domain;
use App\Models\WebsiteFooterLink;
use App\Models\WebsiteForm;
use App\Models\WebsiteFormSubmission;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteRedirect;
use App\Models\WebsiteSetting;
use App\Services\Platform\PublicPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicWebsiteController extends Controller
{
    public function __construct(private readonly PublicPlanService $publicPlans) {}

    public function home(Request $request)
    {
        $host = strtolower($request->getHost());
        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return $this->show($request);
        }
        abort_unless(Domain::where('domain', $host)->exists(), 404);

        return redirect('/dashboard');
    }

    /**
     * Renders a published WebsitePage. The root URL falls back to the
     * default Laravel welcome screen when no "home" page has been published
     * yet, so a fresh install still has a landing page before an admin has
     * touched the CMS.
     */
    public function show(Request $request, string $slug = 'home')
    {
        $path = '/'.ltrim($request->path(), '/');
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $redirect = WebsiteRedirect::where('from_path', $path)->where('is_active', true)->first();
        if ($redirect && $redirect->to_url !== $path) {
            $redirect->increment('hit_count');

            return redirect()->to($redirect->to_url, $redirect->status_code);
        }

        $page = WebsitePage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['sections' => fn ($query) => $query->where('is_hidden', false)])
            ->first();

        if (! $page) {
            if ($slug !== 'home') {
                abort(404);
            }

            return Inertia::render('Welcome', [
                'canLogin' => Route::has('login'),
            ]);
        }

        return $this->render($page);
    }

    public function preview(WebsitePage $page)
    {
        $page->load(['sections' => fn ($query) => $query->where('is_hidden', false)]);

        return $this->render($page, true);
    }

    public function sitemap(): Response
    {
        $pages = WebsitePage::where('status', 'published')->where('robots_index', true)->orderBy('slug')->get(['slug', 'updated_at']);
        $posts = BlogPost::where('status', 'published')->where('published_at', '<=', now())->where('robots_index', true)->get(['slug', 'updated_at']);

        return response(view('website.sitemap', ['pages' => $pages, 'posts' => $posts])->render(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function blog()
    {
        return view('website.blog.index', [
            'posts' => BlogPost::where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->paginate(12),
            ...$this->websiteChrome(),
        ]);
    }

    public function post(string $slug)
    {
        $post = BlogPost::with(['author', 'categories', 'tags'])->where('slug', $slug)->where('status', 'published')->where('published_at', '<=', now())->firstOrFail();

        return view('website.blog.show', ['post' => $post, ...$this->websiteChrome()]);
    }

    public function submitForm(Request $request, WebsiteForm $form): RedirectResponse
    {
        abort_unless($form->is_active, 404);
        $allowed = collect($form->fields)->pluck('name')->filter()->all();
        $rules = collect($form->fields)->mapWithKeys(function (array $field): array {
            $type = match ($field['type'] ?? 'text') {
                'email' => 'email', 'tel' => 'string', 'textarea' => 'string', default => 'string'
            };

            return [($field['name'] ?? '_invalid') => [($field['required'] ?? false) ? 'required' : 'nullable', $type, 'max:5000']];
        })->all();
        $rules['_website'] = ['nullable', 'string', 'max:0'];
        $data = $request->validate($rules);
        WebsiteFormSubmission::create([
            'website_form_id' => $form->id,
            'name' => $data['name'] ?? null, 'email' => $data['email'] ?? null, 'company' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null, 'message' => $data['message'] ?? null,
            'payload' => collect($data)->only($allowed)->all(),
            'utm' => $request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']),
            'referrer' => str($request->headers->get('referer'))->limit(2048),
            'ip_hash' => hash_hmac('sha256', (string) $request->ip(), config('app.key')),
        ]);

        return back()->with('status', 'Thanks — your message has been received.');
    }

    public function robots(): Response
    {
        $rules = WebsiteSetting::where('group', 'seo')->where('key', 'robots_content')->value('value');
        $content = data_get($rules, 'value', "User-agent: *\nAllow: /\nDisallow: /account/\nDisallow: /superadmin/\nSitemap: ".url('/sitemap.xml'));

        return response($content."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function render(WebsitePage $page, bool $preview = false)
    {
        $settings = $this->websiteSettings();

        return view('website.page', [
            'page' => $page,
            'navigation' => WebsiteNavigationItem::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'footerLinks' => WebsiteFooterLink::query()->orderBy('sort_order')->get()->groupBy(fn (WebsiteFooterLink $link) => $link->group ?: 'General'),
            'settings' => $settings,
            'publicPlans' => $this->publicPlans->query()->orderBy('sort_order')->get(),
            'publishedPosts' => BlogPost::query()->where('status', 'published')->where('published_at', '<=', now())->latest('published_at')->limit(6)->get(),
            'preview' => $preview,
        ]);
    }

    private function websiteSettings(): array
    {
        $defaults = [
            'site_name' => config('app.name'),
            'body_font' => 'Inter',
            'heading_font' => 'Inter',
            'default_meta_title_format' => '{title} · {site_name}',
            'twitter_card_type' => 'summary_large_image',
            'primary_color' => '#0f172a',
            'secondary_color' => '#475569',
            'accent_color' => '#4f46e5',
            'button_radius' => '8px',
            'card_radius' => '12px',
            'container_width' => '1152px',
        ];
        $stored = WebsiteSetting::query()->whereIn('group', ['general', 'seo', 'theme'])->get()
            ->mapWithKeys(fn (WebsiteSetting $setting) => [$setting->key => data_get($setting->value, 'value')])
            ->all();

        return [...$defaults, ...array_filter($stored, fn ($value) => $value !== null && $value !== '')];
    }

    private function websiteChrome(): array
    {
        return [
            'settings' => $this->websiteSettings(),
            'navigation' => WebsiteNavigationItem::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'footerLinks' => WebsiteFooterLink::query()->orderBy('sort_order')->get()->groupBy(fn (WebsiteFooterLink $link) => $link->group ?: 'General'),
        ];
    }
}
