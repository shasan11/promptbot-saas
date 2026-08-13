<?php

namespace App\Services\Platform;

use App\Models\WebsitePage;
use App\Models\WebsitePageRevision;
use Illuminate\Support\Facades\DB;

class CmsRevisionService
{
    public function capture(WebsitePage $page, ?int $actorId): WebsitePageRevision
    {
        $page->loadMissing('sections');
        $version = (int) $page->revisions()->max('version') + 1;
        return WebsitePageRevision::create([
            'website_page_id' => $page->id, 'version' => $version, 'created_by' => $actorId,
            'content' => ['page' => $page->only([
                'title', 'slug', 'page_type', 'status', 'template', 'seo', 'canonical_url',
                'robots_index', 'robots_follow', 'open_graph', 'twitter', 'schema_json', 'published_at', 'scheduled_at',
            ]), 'sections' => $page->sections->map->only(['type', 'sort_order', 'content', 'is_hidden'])->all()],
        ]);
    }

    public function restore(WebsitePage $page, WebsitePageRevision $revision, ?int $actorId): void
    {
        abort_unless($revision->website_page_id === $page->id, 404);
        DB::transaction(function () use ($page, $revision, $actorId): void {
            $this->capture($page, $actorId);
            $page->update($revision->content['page']);
            $page->sections()->delete();
            foreach ($revision->content['sections'] as $section) $page->sections()->create($section);
        });
    }
}
