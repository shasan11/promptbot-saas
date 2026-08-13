<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsitePageRequest;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Models\WebsitePageRevision;
use App\Models\WebsiteRedirect;
use App\Models\Media;
use App\Services\Platform\AuditLogService;
use App\Services\Platform\CmsBlockRegistry;
use App\Services\Platform\CmsRevisionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class WebsitePageController extends Controller
{
    public function create(CmsBlockRegistry $blocks): Response
    {
        return Inertia::render('Admin/Website/PageEditor', [
            'page' => null,
            'blockDefinitions' => $blocks->definitions(),
            'media' => Media::query()->latest()->limit(200)->get()->map(fn (Media $media) => ['id' => $media->id, 'filename' => $media->filename, 'url' => $media->url()]),
        ]);
    }

    public function store(WebsitePageRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $page = WebsitePage::create([
            ...$request->safe()->only(['title', 'slug', 'status', 'page_type', 'template', 'canonical_url', 'robots_index', 'robots_follow', 'schema_json', 'scheduled_at']),
            'seo' => array_filter([
                'title' => $request->safe()->offsetGet('seo_title'),
                'description' => $request->safe()->offsetGet('seo_description'),
            ]),
            'published_at' => $request->safe()->offsetGet('status') === 'published' ? now() : null,
            'open_graph' => array_filter(['title' => $request->input('og_title'), 'description' => $request->input('og_description'), 'image' => $request->input('og_image')]),
            'twitter' => array_filter(['title' => $request->input('twitter_title'), 'description' => $request->input('twitter_description'), 'image' => $request->input('twitter_image')]),
            'created_by' => $request->user('central')->id, 'updated_by' => $request->user('central')->id,
        ]);

        $auditLog->record('website_page.created', $page, ['new_values' => $request->validated()]);

        return redirect()->route('superadmin.website.pages.edit', $page)->with('status', 'Page created. Add sections below.');
    }

    public function edit(WebsitePage $page, CmsBlockRegistry $blocks): Response
    {
        return Inertia::render('Admin/Website/PageEditor', [
            'page' => $page->load('sections'),
            'blockDefinitions' => $blocks->definitions(),
            'previewUrl' => URL::temporarySignedRoute('website.preview', now()->addHour(), ['page' => $page]),
            'revisions' => $page->revisions()->limit(20)->get(),
            'media' => Media::query()->latest()->limit(200)->get()->map(fn (Media $media) => ['id' => $media->id, 'filename' => $media->filename, 'url' => $media->url()]),
        ]);
    }

    public function update(WebsitePageRequest $request, WebsitePage $page, AuditLogService $auditLog, CmsRevisionService $revisions): RedirectResponse
    {
        $oldValues = $page->only(['title', 'slug', 'status']);
        $wasPublished = $page->isPublished();
        $oldSlug = $page->slug;
        $revisions->capture($page, $request->user('central')->id);

        $page->update([
            ...$request->safe()->only(['title', 'slug', 'status', 'page_type', 'template', 'canonical_url', 'robots_index', 'robots_follow', 'schema_json', 'scheduled_at']),
            'seo' => array_filter([
                'title' => $request->safe()->offsetGet('seo_title'),
                'description' => $request->safe()->offsetGet('seo_description'),
            ]),
            'open_graph' => array_filter(['title' => $request->input('og_title'), 'description' => $request->input('og_description'), 'image' => $request->input('og_image')]),
            'twitter' => array_filter(['title' => $request->input('twitter_title'), 'description' => $request->input('twitter_description'), 'image' => $request->input('twitter_image')]),
            'updated_by' => $request->user('central')->id,
        ]);

        if (! $wasPublished && $page->isPublished()) {
            $page->forceFill(['published_at' => now()])->save();
        }
        if ($oldSlug !== $page->slug && $wasPublished && $request->boolean('create_slug_redirect', true)) {
            WebsiteRedirect::updateOrCreate(['from_path' => '/'.$oldSlug], ['to_url' => '/'.$page->slug, 'status_code' => 301, 'is_active' => true]);
        }

        $auditLog->record('website_page.updated', $page, [
            'old_values' => $oldValues,
            'new_values' => $request->validated(),
        ]);

        return back()->with('status', 'Page updated.');
    }

    public function destroy(WebsitePage $page, AuditLogService $auditLog): RedirectResponse
    {
        $page->delete();
        $auditLog->record('website_page.deleted', $page, ['severity' => 'warning']);

        return redirect()->route('superadmin.website.index')->with('status', 'Page deleted.');
    }

    /**
     * Replace a page's sections wholesale with the ordered list the editor
     * submits. Simpler and less error-prone than granular per-section
     * create/update/delete endpoints for a "basic" block editor.
     */
    public function updateSections(Request $request, WebsitePage $page, AuditLogService $auditLog, CmsBlockRegistry $blocks, CmsRevisionService $revisions): RedirectResponse
    {
        abort_unless((bool) $request->user('central')?->can('website.manage'), 403);

        $validated = $request->validate([
            'sections' => ['present', 'array', 'max:100'],
            'sections.*.type' => ['required', 'in:'.implode(',', array_column($blocks->definitions(), 'key'))],
            'sections.*.content' => ['present', 'array', 'max:100'],
            'sections.*.is_hidden' => ['boolean'],
        ]);

        if (collect($validated['sections'])->contains('type', 'custom_html')) {
            abort_unless($request->user('central')->can('website.custom_html'), 403);
        }

        $revisions->capture($page, $request->user('central')->id);

        DB::transaction(function () use ($page, $validated, $blocks): void {
            $page->sections()->delete();

            foreach (array_values($validated['sections']) as $index => $section) {
                $page->sections()->create([
                    'type' => $section['type'],
                    'sort_order' => $index,
                    'content' => $blocks->sanitize($section['type'], $section['content']),
                    'is_hidden' => (bool) ($section['is_hidden'] ?? false),
                ]);
            }
        });

        $auditLog->record('website_page.sections_updated', $page, ['new_values' => ['section_count' => count($validated['sections'])]]);

        return back()->with('status', 'Sections saved.');
    }

    public function restore(Request $request, WebsitePage $page, WebsitePageRevision $revision, CmsRevisionService $revisions, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless($request->user('central')->can('website.manage'), 403);
        $revisions->restore($page, $revision, $request->user('central')->id);
        $auditLog->record('website_page.revision_restored', $page, ['new_values' => ['revision_id' => $revision->id, 'version' => $revision->version]]);
        return back()->with('status', "Revision {$revision->version} restored.");
    }
}
