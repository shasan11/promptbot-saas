<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\WebsiteFooterLink;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    private const SETTINGS_FIELDS = ['site_name', 'logo_url', 'primary_color', 'contact_email', 'social_twitter', 'social_linkedin'];

    public function index(): Response
    {
        $settings = WebsiteSetting::query()->where('group', 'general')->get()->mapWithKeys(
            fn (WebsiteSetting $setting) => [$setting->key => data_get($setting->value, 'value')]
        );

        return Inertia::render('Admin/Website/Index', [
            'pages' => WebsitePage::query()->withCount('sections')->orderBy('title')->get(),
            'navigation' => WebsiteNavigationItem::query()->orderBy('sort_order')->get(),
            'footerLinks' => WebsiteFooterLink::query()->orderBy('sort_order')->get(),
            'settings' => collect(self::SETTINGS_FIELDS)->mapWithKeys(fn (string $key) => [$key => $settings[$key] ?? ''])->all(),
        ]);
    }

    public function updateSettings(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:255'],
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'social_twitter' => ['nullable', 'string', 'max:2048'],
            'social_linkedin' => ['nullable', 'string', 'max:2048'],
        ]);

        foreach ($validated as $key => $value) {
            WebsiteSetting::updateOrCreate(
                ['group' => 'general', 'key' => $key],
                ['value' => ['value' => $value]]
            );
        }

        $auditLog->record('website_settings.updated', null, ['entity_type' => 'WebsiteSetting', 'new_values' => $validated]);

        return back()->with('status', 'Website settings updated.');
    }

    public function storeNavigationItem(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2048'],
        ]);

        $item = WebsiteNavigationItem::create([
            ...$validated,
            'sort_order' => (WebsiteNavigationItem::max('sort_order') ?? 0) + 1,
            'is_active' => true,
        ]);

        $auditLog->record('website_navigation.created', $item, ['new_values' => $validated]);

        return back()->with('status', 'Navigation link added.');
    }

    public function updateNavigationItem(Request $request, WebsiteNavigationItem $item, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ]);

        $item->update($validated);
        $auditLog->record('website_navigation.updated', $item, ['new_values' => $validated]);

        return back()->with('status', 'Navigation link updated.');
    }

    public function destroyNavigationItem(WebsiteNavigationItem $item, AuditLogService $auditLog): RedirectResponse
    {
        $item->delete();
        $auditLog->record('website_navigation.deleted', $item);

        return back()->with('status', 'Navigation link removed.');
    }

    public function storeFooterLink(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:2048'],
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
            'url' => ['required', 'string', 'max:2048'],
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

    public function uploadMedia(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'image', 'max:4096'],
        ]);

        $path = $request->file('file')->store('website-media', 'public');
        $media = Media::create([
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'size' => $request->file('file')->getSize(),
            'metadata' => ['original_name' => $request->file('file')->getClientOriginalName()],
        ]);

        return response()->json(['url' => $media->url()]);
    }
}
