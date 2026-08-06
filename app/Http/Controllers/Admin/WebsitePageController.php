<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WebsitePageRequest;
use App\Models\WebsitePage;
use App\Models\WebsiteSection;
use App\Services\Platform\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WebsitePageController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Website/PageEditor', [
            'page' => null,
            'sectionTypes' => WebsiteSection::TYPES,
        ]);
    }

    public function store(WebsitePageRequest $request, AuditLogService $auditLog): RedirectResponse
    {
        $page = WebsitePage::create([
            ...$request->safe()->only(['title', 'slug', 'status']),
            'seo' => array_filter([
                'title' => $request->safe()->offsetGet('seo_title'),
                'description' => $request->safe()->offsetGet('seo_description'),
            ]),
            'published_at' => $request->safe()->offsetGet('status') === 'published' ? now() : null,
        ]);

        $auditLog->record('website_page.created', $page, ['new_values' => $request->validated()]);

        return redirect()->route('superadmin.website.pages.edit', $page)->with('status', 'Page created. Add sections below.');
    }

    public function edit(WebsitePage $page): Response
    {
        return Inertia::render('Admin/Website/PageEditor', [
            'page' => $page->load('sections'),
            'sectionTypes' => WebsiteSection::TYPES,
        ]);
    }

    public function update(WebsitePageRequest $request, WebsitePage $page, AuditLogService $auditLog): RedirectResponse
    {
        $oldValues = $page->only(['title', 'slug', 'status']);
        $wasPublished = $page->isPublished();

        $page->update([
            ...$request->safe()->only(['title', 'slug', 'status']),
            'seo' => array_filter([
                'title' => $request->safe()->offsetGet('seo_title'),
                'description' => $request->safe()->offsetGet('seo_description'),
            ]),
        ]);

        if (! $wasPublished && $page->isPublished()) {
            $page->forceFill(['published_at' => now()])->save();
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
    public function updateSections(Request $request, WebsitePage $page, AuditLogService $auditLog): RedirectResponse
    {
        abort_unless((bool) $request->user('central')?->can('website.manage'), 403);

        $validated = $request->validate([
            'sections' => ['present', 'array'],
            'sections.*.type' => ['required', 'in:'.implode(',', WebsiteSection::TYPES)],
            'sections.*.content' => ['present', 'array'],
        ]);

        DB::transaction(function () use ($page, $validated): void {
            $page->sections()->delete();

            foreach (array_values($validated['sections']) as $index => $section) {
                $page->sections()->create([
                    'type' => $section['type'],
                    'sort_order' => $index,
                    'content' => $this->sanitizeContent($section['type'], $section['content']),
                ]);
            }
        });

        $auditLog->record('website_page.sections_updated', $page, ['new_values' => ['section_count' => count($validated['sections'])]]);

        return back()->with('status', 'Sections saved.');
    }

    private function sanitizeContent(string $type, array $content): array
    {
        $allowedKeys = match ($type) {
            'rich_text' => ['html'],
            'hero' => ['heading', 'subheading', 'image_url', 'button_label', 'button_url'],
            'cta' => ['heading', 'button_label', 'button_url'],
            default => [],
        };

        return array_intersect_key($content, array_flip($allowedKeys));
    }
}
