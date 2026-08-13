<?php

namespace Tests\Feature\Platform;

use App\Models\WebsitePage;
use App\Models\WebsiteRedirect;
use App\Models\BlogPost;
use App\Models\WebsiteForm;
use App\Models\WebsiteCategory;
use App\Models\WebsiteTag;
use App\Services\Platform\CmsBlockRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class CmsPublishingTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_draft_preview_requires_a_valid_signature(): void
    {
        $page = WebsitePage::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
        $page->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Private preview']]);

        $this->get(route('website.preview', $page))->assertForbidden();
        $this->get(URL::temporarySignedRoute('website.preview', now()->addMinute(), ['page' => $page]))
            ->assertOk()->assertSee('Private preview');
        $this->get('/draft')->assertNotFound();
    }

    public function test_sitemap_includes_only_published_indexable_pages(): void
    {
        WebsitePage::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'robots_index' => true]);
        WebsitePage::create(['title' => 'Hidden', 'slug' => 'hidden', 'status' => 'published', 'robots_index' => false]);
        WebsitePage::create(['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'robots_index' => true]);

        $this->get('/sitemap.xml')->assertOk()->assertSee(url('/'), false)->assertDontSee('/hidden')->assertDontSee('/draft');
    }

    public function test_block_registry_sanitizes_html_and_nested_urls(): void
    {
        $registry = app(CmsBlockRegistry::class);
        $rich = $registry->sanitize('rich_text', ['html' => '<p onclick="steal()">Safe</p><script>alert(1)</script>']);
        $features = $registry->sanitize('feature_grid', ['items' => [['title' => '<b>Feature</b>', 'url' => 'javascript:alert(1)']]]);

        $this->assertStringNotContainsString('onclick', $rich['html']);
        $this->assertStringNotContainsString('<script', $rich['html']);
        $this->assertSame('Feature', $features['items'][0]['title']);
        $this->assertSame('', $features['items'][0]['url']);
    }

    public function test_saving_blocks_creates_revision_and_restore_reverts_content(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);
        $page = WebsitePage::create(['title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
        $page->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Version one']]);

        $this->actingAs($admin, 'central')->put(route('superadmin.website.pages.sections', $page), [
            'sections' => [['type' => 'hero', 'content' => ['heading' => 'Version two']]],
        ])->assertRedirect();
        $revision = $page->revisions()->firstOrFail();
        $this->assertSame('Version one', $revision->content['sections'][0]['content']['heading']);

        $this->actingAs($admin, 'central')->post(route('superadmin.website.pages.revisions.restore', [$page, $revision]))->assertRedirect();
        $this->assertSame('Version one', $page->fresh()->sections()->first()->content['heading']);
    }

    public function test_redirect_manager_rejects_self_and_two_node_loops(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);
        $this->actingAs($admin, 'central')->post(route('superadmin.website.redirects.store'), ['from_path' => '/old', 'to_url' => '/old', 'status_code' => 301])->assertStatus(422);
        WebsiteRedirect::create(['from_path' => '/a', 'to_url' => '/b', 'status_code' => 301, 'is_active' => true]);
        $this->actingAs($admin, 'central')->post(route('superadmin.website.redirects.store'), ['from_path' => '/b', 'to_url' => '/a', 'status_code' => 301])->assertStatus(422);
    }

    public function test_published_blog_is_sanitized_public_and_in_sitemap(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);
        $this->actingAs($admin, 'central')->post(route('superadmin.website.blog.store'), [
            'title' => 'Safe article', 'slug' => 'safe-article', 'excerpt' => 'A useful article',
            'content' => '<p onclick="bad()">Useful</p><script>alert(1)</script>', 'status' => 'published',
        ])->assertRedirect();

        $post = BlogPost::firstOrFail();
        $this->assertStringNotContainsString('onclick', $post->content);
        $this->assertStringNotContainsString('<script', $post->content);
        $this->get('/blog/safe-article')->assertOk()->assertSee('Useful')->assertDontSee('alert(1)');
        $this->get('/sitemap.xml')->assertSee('/blog/safe-article');
    }

    public function test_active_public_form_captures_lead_with_hashed_ip_and_attribution(): void
    {
        $form = WebsiteForm::create(['name' => 'Contact', 'slug' => 'contact', 'is_active' => true, 'fields' => [
            ['name' => 'name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'type' => 'email', 'required' => true],
            ['name' => 'message', 'type' => 'textarea', 'required' => true],
        ]]);

        $this->withHeader('referer', 'https://example.test/pricing?utm_source=search')->post(route('website.forms.submit', $form), [
            'name' => 'Buyer', 'email' => 'buyer@example.test', 'message' => 'Please call me', 'utm_source' => 'search',
        ])->assertRedirect();

        $this->assertDatabaseHas('website_form_submissions', ['website_form_id' => $form->id, 'email' => 'buyer@example.test', 'status' => 'new']);
        $this->assertNotEmpty($form->submissions()->firstOrFail()->getRawOriginal('ip_hash'));
    }

    public function test_admin_can_manage_blog_taxonomy_and_assign_it_to_a_post(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);
        $this->actingAs($admin, 'central')->post(route('superadmin.website.categories.store'), ['name' => 'Guides', 'slug' => 'guides'])->assertRedirect();
        $this->actingAs($admin, 'central')->post(route('superadmin.website.tags.store'), ['name' => 'Billing', 'slug' => 'billing'])->assertRedirect();
        $category = WebsiteCategory::firstOrFail();
        $tag = WebsiteTag::firstOrFail();

        $this->actingAs($admin, 'central')->post(route('superadmin.website.blog.store'), [
            'title' => 'Billing guide', 'slug' => 'billing-guide', 'content' => '<p>Guide</p>', 'status' => 'draft',
            'category_ids' => [$category->id], 'tag_ids' => [$tag->id],
        ])->assertRedirect();

        $post = BlogPost::firstOrFail();
        $this->assertTrue($post->categories()->whereKey($category->id)->exists());
        $this->assertTrue($post->tags()->whereKey($tag->id)->exists());
    }

    public function test_form_builder_validates_and_persists_unique_fields(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);
        $form = WebsiteForm::create(['name' => 'Contact', 'slug' => 'contact', 'is_active' => true, 'fields' => [['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]]]);
        $payload = ['name' => 'Sales lead', 'slug' => 'sales-lead', 'is_active' => true, 'fields' => [
            ['name' => 'email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
        ]];

        $this->actingAs($admin, 'central')->put(route('superadmin.website.forms.update', $form), $payload)->assertRedirect();
        $this->assertSame('phone', $form->fresh()->fields[1]['name']);

        $payload['fields'][1]['name'] = 'email';
        $this->actingAs($admin, 'central')->put(route('superadmin.website.forms.update', $form), $payload)->assertStatus(422);
    }
}
