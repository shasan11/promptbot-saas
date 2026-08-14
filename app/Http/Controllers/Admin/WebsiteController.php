<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\WebsiteFooterLink;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use App\Models\WebsiteRedirect;
use App\Models\BlogPost;
use App\Models\WebsiteForm;
use App\Models\WebsiteFormSubmission;
use App\Models\WebsiteCategory;
use App\Models\WebsiteTag;
use App\Models\WebsiteSection;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\CmsBlockRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WebsiteController extends Controller
{
    private const SETTINGS_FIELDS = [
        'site_name', 'logo_url', 'logo_dark_url', 'favicon_url', 'primary_color', 'secondary_color', 'accent_color',
        'footer_description', 'copyright_text', 'heading_font', 'body_font', 'button_radius', 'card_radius', 'container_width',
        'contact_email', 'social_twitter', 'social_linkedin', 'default_meta_title_format', 'default_description',
        'default_og_image', 'canonical_base_url', 'robots_content', 'google_verification', 'bing_verification',
        'twitter_card_type',
        'google_analytics_id', 'google_tag_manager_id', 'meta_pixel_id',
    ];

    public function index(): Response
    {
        $settings = WebsiteSetting::query()->whereIn('group', ['general', 'seo', 'theme'])->get()->mapWithKeys(
            fn (WebsiteSetting $setting) => [$setting->key => data_get($setting->value, 'value')]
        );

        return Inertia::render('Admin/Website/Index', [
            'pages' => WebsitePage::query()->withCount('sections')->orderBy('title')->get(),
            'navigation' => WebsiteNavigationItem::query()->orderBy('sort_order')->get(),
            'footerLinks' => WebsiteFooterLink::query()->orderBy('sort_order')->get(),
            'settings' => collect(self::SETTINGS_FIELDS)->mapWithKeys(fn (string $key) => [$key => $settings[$key] ?? ''])->all(),
            'media' => Media::query()->latest()->limit(100)->get()->map(fn (Media $media) => [...$media->toArray(), 'url' => $media->url()]),
            'redirects' => WebsiteRedirect::query()->latest()->limit(50)->get(),
            'blogPosts' => BlogPost::query()->with(['author:id,name', 'categories:id,name', 'tags:id,name'])->latest()->limit(50)->get(),
            'categories' => WebsiteCategory::query()->orderBy('name')->get(),
            'tags' => WebsiteTag::query()->orderBy('name')->get(),
            'forms' => WebsiteForm::query()->withCount('submissions')->orderBy('name')->get(),
            'leads' => WebsiteFormSubmission::query()->with('form:id,name')->latest()->limit(100)->get(),
        ]);
    }

    public function updateSettings(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'logo_dark_url' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()], 'favicon_url' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'accent_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'footer_description' => ['nullable', 'string', 'max:500'], 'copyright_text' => ['nullable', 'string', 'max:255'],
            'heading_font' => ['nullable', Rule::in(['Inter', 'Manrope', 'Poppins', 'Roboto', 'system-ui'])],
            'body_font' => ['nullable', Rule::in(['Inter', 'Manrope', 'Poppins', 'Roboto', 'system-ui'])],
            'button_radius' => ['nullable', Rule::in(['0', '4px', '8px', '12px', '9999px'])],
            'card_radius' => ['nullable', Rule::in(['0', '8px', '12px', '16px', '24px'])],
            'container_width' => ['nullable', Rule::in(['1024px', '1152px', '1280px', '1440px'])],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'social_twitter' => ['nullable', 'url:http,https', 'max:2048'],
            'social_linkedin' => ['nullable', 'url:http,https', 'max:2048'],
            'default_meta_title_format' => ['nullable', 'string', 'max:255'], 'default_description' => ['nullable', 'string', 'max:500'],
            'default_og_image' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()], 'canonical_base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'twitter_card_type' => ['nullable', Rule::in(['summary', 'summary_large_image'])],
            'robots_content' => ['nullable', 'string', 'max:5000'], 'google_verification' => ['nullable', 'string', 'max:255'],
            'bing_verification' => ['nullable', 'string', 'max:255'], 'google_analytics_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/'],
            'google_tag_manager_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]+$/'], 'meta_pixel_id' => ['nullable', 'digits_between:5,30'],
            'logo_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'logo_dark_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon_file' => ['nullable', 'file', 'mimes:png,ico,webp', 'max:512'],
        ]);

        $uploads = collect([
            'logo_file' => ['setting' => 'logo_url', 'group' => 'general'],
            'logo_dark_file' => ['setting' => 'logo_dark_url', 'group' => 'theme'],
            'favicon_file' => ['setting' => 'favicon_url', 'group' => 'theme'],
        ])->filter(fn (array $definition, string $key) => $request->hasFile($key));
        $validated = collect($validated)->except(['logo_file', 'logo_dark_file', 'favicon_file'])->all();

        foreach ($validated as $key => $value) {
            $group = in_array($key, ['primary_color', 'secondary_color', 'accent_color', 'logo_dark_url', 'favicon_url', 'heading_font', 'body_font', 'button_radius', 'card_radius', 'container_width'], true) ? 'theme'
                : (in_array($key, ['default_meta_title_format', 'default_description', 'default_og_image', 'canonical_base_url', 'robots_content', 'google_verification', 'bing_verification', 'google_analytics_id', 'google_tag_manager_id', 'meta_pixel_id', 'twitter_card_type'], true) ? 'seo' : 'general');
            WebsiteSetting::updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => ['value' => $value]]
            );
        }

        foreach ($uploads as $fileKey => $definition) {
            $path = $request->file($fileKey)->store('website/branding', 'public');
            $url = Storage::disk('public')->url($path);
            WebsiteSetting::updateOrCreate(
                ['group' => $definition['group'], 'key' => $definition['setting']],
                ['value' => ['value' => $url]],
            );
            $validated[$definition['setting']] = $url;
        }

        $auditLog->record('website_settings.updated', null, ['entity_type' => 'WebsiteSetting', 'new_values' => $validated]);

        return back()->with('status', 'Website settings updated.');
    }

    public function storeNavigationItem(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $this->validateNavigation($request);

        $item = WebsiteNavigationItem::create([
            ...$this->normalizeNavigation($validated),
            'sort_order' => (WebsiteNavigationItem::max('sort_order') ?? 0) + 1,
        ]);

        $auditLog->record('website_navigation.created', $item, ['new_values' => $validated]);

        return back()->with('status', 'Navigation link added.');
    }

    public function updateNavigationItem(Request $request, WebsiteNavigationItem $item, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $this->validateNavigation($request, $item);

        $item->update($this->normalizeNavigation($validated));
        $auditLog->record('website_navigation.updated', $item, ['new_values' => $validated]);

        return back()->with('status', 'Navigation link updated.');
    }

    public function reorderNavigation(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'max:200'],
            'ordered_ids.*' => ['required', 'uuid', 'distinct', 'exists:website_navigation_items,id'],
        ]);

        foreach ($data['ordered_ids'] as $position => $id) {
            WebsiteNavigationItem::whereKey($id)->update(['sort_order' => $position + 1]);
        }
        $auditLog->record('website_navigation.reordered', null, ['entity_type' => 'WebsiteNavigationItem', 'new_values' => $data]);

        return back()->with('status', 'Navigation order updated.');
    }

    public function destroyNavigationItem(WebsiteNavigationItem $item, AuditLogService $auditLog): RedirectResponse
    {
        WebsiteNavigationItem::where('parent_id', $item->id)->update(['parent_id' => null]);
        $item->delete();
        $auditLog->record('website_navigation.deleted', $item);

        return back()->with('status', 'Navigation link removed.');
    }

    public function storeFooterLink(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2048', $this->safePublicUrl()],
            'group' => ['nullable', 'string', 'max:100'],
        ]);

        $link = WebsiteFooterLink::create([
            ...$validated,
            'sort_order' => (WebsiteFooterLink::max('sort_order') ?? 0) + 1,
        ]);

        $auditLog->record('website_footer_link.created', $link, ['new_values' => $validated]);

        return back()->with('status', 'Footer link added.');
    }

    public function updateFooterLink(Request $request, WebsiteFooterLink $link, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2048', $this->safePublicUrl()],
            'group' => ['nullable', 'string', 'max:100'],
        ]);

        $link->update($validated);
        $auditLog->record('website_footer_link.updated', $link, ['new_values' => $validated]);

        return back()->with('status', 'Footer link updated.');
    }

    public function destroyFooterLink(WebsiteFooterLink $link, AuditLogService $auditLog): RedirectResponse
    {
        $link->delete();
        $auditLog->record('website_footer_link.deleted', $link);

        return back()->with('status', 'Footer link removed.');
    }

    public function reorderFooterLinks(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'ordered_ids' => ['required', 'array', 'max:200'],
            'ordered_ids.*' => ['required', 'uuid', 'distinct', 'exists:website_footer_links,id'],
        ]);
        foreach ($data['ordered_ids'] as $position => $id) WebsiteFooterLink::whereKey($id)->update(['sort_order' => $position + 1]);
        $auditLog->record('website_footer_links.reordered', null, ['entity_type' => 'WebsiteFooterLink', 'new_values' => $data]);
        return back()->with('status', 'Footer order updated.');
    }

    public function uploadMedia(Request $request, AuditLogService $auditLog): JsonResponse|RedirectResponse
    {
        $request->validate([
            'file' => $this->mediaFileRules(),
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $path = $request->file('file')->store('website-media', 'public');
        [$width, $height] = getimagesize($request->file('file')->getRealPath()) ?: [null, null];
        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => basename($path),
            'alt_text' => $request->string('alt_text')->toString() ?: null,
            'caption' => $request->string('caption')->toString() ?: null,
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
            'width' => $width, 'height' => $height,
            'uploaded_by' => $request->user('central')->id,
            'metadata' => ['original_name' => $request->file('file')->getClientOriginalName()],
        ]);
        $auditLog->record('website_media.uploaded', $media, ['new_values' => $media->only(['path', 'filename', 'mime_type', 'size', 'width', 'height'])]);

        return $request->header('X-Inertia')
            ? back()->with('status', 'Media uploaded.')
            : response()->json(['url' => $media->url()]);
    }

    public function updateMedia(Request $request, Media $media, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['nullable', ...array_slice($this->mediaFileRules(), 1)],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);
        $old = $media->only(['path', 'alt_text', 'caption', 'mime_type', 'size']);
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            abort_unless($file->getMimeType() === $media->mime_type, 422, 'Replacement media must use the same image format so existing URLs remain valid.');
            [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
            abort_unless(Storage::disk($media->disk)->put($media->path, fopen($file->getRealPath(), 'rb')), 500, 'The replacement image could not be stored.');
            $media->fill([
                'size' => $file->getSize(), 'width' => $width, 'height' => $height,
                'metadata' => ['original_name' => $file->getClientOriginalName()],
            ]);
            $media->save();
        }
        $media->update(['alt_text' => $data['alt_text'] ?? null, 'caption' => $data['caption'] ?? null]);
        $auditLog->record('website_media.updated', $media, ['old_values' => $old, 'new_values' => $media->only(['path', 'alt_text', 'caption', 'mime_type', 'size'])]);
        return back()->with('status', 'Media updated.');
    }

    public function destroyMedia(Media $media, AuditLogService $auditLog): RedirectResponse
    {
        abort_if($this->mediaIsUsed($media), 422, 'This media item is still referenced by website content. Replace those references before deleting it.');
        $snapshot = $media->only(['path', 'filename', 'mime_type', 'size']);
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();
        $auditLog->record('website_media.deleted', $media, ['old_values' => $snapshot, 'severity' => 'warning']);
        return back()->with('status', 'Unused media deleted.');
    }

    public function storeRedirect(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate(['from_path' => ['required', 'string', 'max:2048', 'starts_with:/', 'not_regex:/^\/\//', 'unique:website_redirects,from_path'], 'to_url' => ['required', 'string', 'max:2048', $this->safePublicUrl()], 'status_code' => ['required', 'in:301,302']]);
        abort_if(rtrim($data['from_path'], '/') === rtrim($data['to_url'], '/'), 422, 'A redirect cannot point to itself.');
        abort_if(WebsiteRedirect::where('from_path', $data['to_url'])->where('to_url', $data['from_path'])->exists(), 422, 'This redirect would create a loop.');
        $redirect = WebsiteRedirect::create([...$data, 'is_active' => true]);
        $auditLog->record('website_redirect.created', $redirect, ['new_values' => $data]);
        return back()->with('status', 'Redirect created.');
    }

    public function destroyRedirect(WebsiteRedirect $redirect, AuditLogService $auditLog): RedirectResponse
    {
        $redirect->delete();
        $auditLog->record('website_redirect.deleted', $redirect);
        return back()->with('status', 'Redirect removed.');
    }

    public function storeBlogPost(Request $request, CmsBlockRegistry $blocks, AuditLogService $auditLog): RedirectResponse
    {
        $data = $this->validateBlogPost($request);
        $post = BlogPost::create([
            ...collect($data)->except(['seo_title', 'seo_description', 'category_ids', 'tag_ids'])->all(),
            'content' => $blocks->sanitize('rich_text', ['html' => $data['content']])['html'],
            'seo' => ['title' => $data['seo_title'] ?? null, 'description' => $data['seo_description'] ?? null],
            'published_at' => $data['status'] === 'published' ? now() : null,
            'author_id' => $request->user('central')->id,
        ]);
        $post->categories()->sync($data['category_ids'] ?? []);
        $post->tags()->sync($data['tag_ids'] ?? []);
        $auditLog->record('website_blog.created', $post, ['new_values' => collect($data)->except('content')->all()]);
        return back()->with('status', 'Blog post saved.');
    }

    public function updateBlogPost(Request $request, BlogPost $post, CmsBlockRegistry $blocks, AuditLogService $auditLog): RedirectResponse
    {
        $data = $this->validateBlogPost($request, $post);
        $post->update([
            ...collect($data)->except(['seo_title', 'seo_description', 'category_ids', 'tag_ids'])->all(),
            'content' => $blocks->sanitize('rich_text', ['html' => $data['content']])['html'],
            'seo' => ['title' => $data['seo_title'] ?? null, 'description' => $data['seo_description'] ?? null],
            'published_at' => $data['status'] === 'published' ? ($post->published_at ?? now()) : null,
        ]);
        $post->categories()->sync($data['category_ids'] ?? []);
        $post->tags()->sync($data['tag_ids'] ?? []);
        $auditLog->record('website_blog.updated', $post, ['new_values' => collect($data)->except('content')->all()]);
        return back()->with('status', 'Blog post updated.');
    }

    public function destroyBlogPost(BlogPost $post, AuditLogService $auditLog): RedirectResponse
    {
        $post->delete();
        $auditLog->record('website_blog.deleted', $post);
        return back()->with('status', 'Blog post deleted.');
    }

    public function storeForm(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash:ascii', 'unique:website_forms,slug']]);
        $form = WebsiteForm::create([...$data, 'is_active' => true, 'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea', 'required' => true],
        ]]);
        $auditLog->record('website_form.created', $form, ['new_values' => $data]);
        return back()->with('status', 'Lead form created.');
    }

    public function updateForm(Request $request, WebsiteForm $form, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:180', 'unique:website_forms,slug,'.$form->id],
            'is_active' => ['required', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:30'],
            'fields.*.name' => ['required', 'alpha_dash:ascii', 'max:80'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.type' => ['required', 'in:text,email,tel,textarea'],
            'fields.*.required' => ['required', 'boolean'],
        ]);
        abort_if(collect($data['fields'])->pluck('name')->duplicates()->isNotEmpty(), 422, 'Field names must be unique.');
        $old = $form->only(['name', 'slug', 'is_active', 'fields']);
        $form->update($data);
        $auditLog->record('website_form.updated', $form, ['old_values' => $old, 'new_values' => $data]);
        return back()->with('status', 'Lead form updated.');
    }

    public function storeCategory(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        return $this->storeTaxonomy($request, WebsiteCategory::class, 'category', $auditLog);
    }

    public function destroyCategory(WebsiteCategory $category, AuditLogService $auditLog): RedirectResponse
    {
        $category->delete();
        $auditLog->record('website_category.deleted', $category);
        return back()->with('status', 'Category removed.');
    }

    public function storeTag(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        return $this->storeTaxonomy($request, WebsiteTag::class, 'tag', $auditLog);
    }

    public function destroyTag(WebsiteTag $tag, AuditLogService $auditLog): RedirectResponse
    {
        $tag->delete();
        $auditLog->record('website_tag.deleted', $tag);
        return back()->with('status', 'Tag removed.');
    }

    public function updateLead(Request $request, WebsiteFormSubmission $submission, AuditLogService $auditLog): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:new,contacted,qualified,won,lost,closed,spam'], 'notes' => ['nullable', 'string', 'max:10000']]);
        $submission->update($data);
        $auditLog->record('website_lead.updated', $submission, ['new_values' => $data]);
        return back()->with('status', 'Lead updated.');
    }

    public function exportLeads(Request $request): StreamedResponse
    {
        abort_unless($request->user('central')?->can('website.manage'), 403);
        $status = $request->validate(['status' => ['nullable', 'in:new,contacted,qualified,won,lost,closed,spam']])['status'] ?? null;
        $leads = WebsiteFormSubmission::with('form:id,name')->when($status, fn ($query) => $query->where('status', $status))->latest()->cursor();
        return response()->streamDownload(function () use ($leads): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Submitted at', 'Form', 'Name', 'Email', 'Company', 'Phone', 'Message', 'Status', 'Notes']);
            foreach ($leads as $lead) {
                $safe = fn (mixed $value): string => preg_match('/^[=+\-@]/', (string) $value) ? "'".(string) $value : (string) $value;
                fputcsv($output, array_map($safe, [$lead->created_at, $lead->form?->name, $lead->name, $lead->email, $lead->company, $lead->phone, $lead->message, $lead->status, $lead->notes]));
            }
            fclose($output);
        }, 'website-leads-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateBlogPost(Request $request, ?BlogPost $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:180', 'unique:blog_posts,slug'.($post ? ','.$post->id : '')],
            'excerpt' => ['nullable', 'string', 'max:1000'], 'content' => ['required', 'string', 'max:200000'],
            'status' => ['required', 'in:draft,published,scheduled'], 'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:500'],
            'featured_image' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'], 'robots_index' => ['required', 'boolean'],
            'category_ids' => ['array'], 'category_ids.*' => ['uuid', 'exists:website_categories,id'],
            'tag_ids' => ['array'], 'tag_ids.*' => ['uuid', 'exists:website_tags,id'],
        ]);
    }

    private function validateNavigation(Request $request, ?WebsiteNavigationItem $item = null): array
    {
        $request->merge([
            'type' => $request->input('type', $item?->type ?? 'external'),
            'menu_group' => $request->input('menu_group', $item?->menu_group ?? 'header'),
            'open_new_tab' => $request->boolean('open_new_tab', $item?->open_new_tab ?? false),
            'is_active' => $request->boolean('is_active', $item?->is_active ?? true),
            'style' => $request->input('style', $item?->style ?? 'link'),
        ]);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['internal', 'external', 'dropdown', 'button'])],
            'url' => ['nullable', 'string', 'max:2048', $this->safePublicUrl()],
            'menu_group' => ['required', Rule::in(['header', 'mobile', 'footer'])],
            'website_page_id' => ['nullable', 'uuid', 'exists:website_pages,id'],
            'parent_id' => ['nullable', 'uuid', 'exists:website_navigation_items,id'],
            'open_new_tab' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'style' => ['required', Rule::in(['link', 'button'])],
        ]);

        abort_if($item && ($data['parent_id'] ?? null) === $item->id, 422, 'A navigation item cannot be its own parent.');
        abort_if($data['type'] === 'internal' && empty($data['website_page_id']), 422, 'Choose a website page for an internal link.');
        abort_if(in_array($data['type'], ['external', 'button'], true) && empty($data['url']), 422, 'Enter a URL for an external link or button.');
        abort_if($data['type'] === 'dropdown' && ! empty($data['parent_id']), 422, 'A dropdown cannot be nested inside another dropdown.');

        if (! empty($data['parent_id'])) {
            $parent = WebsiteNavigationItem::findOrFail($data['parent_id']);
            abort_unless($parent->type === 'dropdown', 422, 'Child links can only be assigned to a dropdown.');
            abort_unless($parent->menu_group === $data['menu_group'], 422, 'A child link must use the same menu group as its parent dropdown.');
        }

        return $data;
    }

    private function normalizeNavigation(array $data): array
    {
        if ($data['type'] === 'internal') {
            $page = WebsitePage::findOrFail($data['website_page_id']);
            $data['url'] = $page->slug === 'home' ? '/' : '/'.$page->slug;
            $data['open_new_tab'] = false;
        } elseif ($data['type'] === 'dropdown') {
            $data['url'] = '#';
            $data['website_page_id'] = null;
            $data['open_new_tab'] = false;
            $data['style'] = 'link';
        } else {
            $data['website_page_id'] = null;
            if ($data['type'] === 'button') $data['style'] = 'button';
        }

        return $data;
    }

    private function storeTaxonomy(Request $request, string $model, string $type, AuditLogService $auditLog): RedirectResponse
    {
        $table = (new $model)->getTable();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:120', 'unique:'.$table.',slug'],
        ]);
        $item = $model::create($data);
        $auditLog->record('website_'.$type.'.created', $item, ['new_values' => $data]);
        return back()->with('status', ucfirst($type).' created.');
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

    private function mediaFileRules(): array
    {
        return ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'dimensions:max_width=6000,max_height=6000', 'max:4096'];
    }

    private function mediaIsUsed(Media $media): bool
    {
        foreach (array_unique([$media->path, $media->url()]) as $needle) {
            $like = '%'.$needle.'%';
            if (WebsiteSection::where('content', 'like', $like)->exists()
                || WebsiteSetting::where('value', 'like', $like)->exists()
                || WebsitePage::where('open_graph', 'like', $like)->orWhere('twitter', 'like', $like)->exists()
                || BlogPost::where('content', 'like', $like)->orWhere('featured_image', 'like', $like)->exists()) return true;
        }
        return false;
    }
}
