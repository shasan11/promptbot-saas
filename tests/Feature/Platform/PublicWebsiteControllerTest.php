<?php

namespace Tests\Feature\Platform;

use App\Models\BlogPost;
use App\Models\WebsiteFooterLink;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use Database\Seeders\WebsiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicWebsiteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_falls_back_to_the_welcome_screen_when_no_home_page_is_published(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page->component('Welcome'));
    }

    public function test_a_published_home_page_renders_its_sections(): void
    {
        $page = WebsitePage::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()]);
        $page->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Welcome to Acme']]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Welcome to Acme');
    }

    public function test_a_draft_page_is_not_publicly_reachable(): void
    {
        WebsitePage::create(['title' => 'Secret', 'slug' => 'secret', 'status' => 'draft']);

        $this->get('/secret')->assertNotFound();
    }

    public function test_an_unknown_slug_is_not_found(): void
    {
        $this->get('/does-not-exist')->assertNotFound();
    }

    public function test_a_published_non_home_page_is_reachable_by_slug(): void
    {
        $page = WebsitePage::create(['title' => 'About', 'slug' => 'about', 'status' => 'published', 'published_at' => now()]);
        $page->sections()->create(['type' => 'rich_text', 'sort_order' => 0, 'content' => ['html' => '<p>About us copy</p>']]);

        $this->get('/about')->assertOk()->assertSee('About us copy', false);
    }

    public function test_starter_site_is_truthful_cms_managed_and_idempotent(): void
    {
        $this->seed(WebsiteSeeder::class);
        $home = WebsitePage::where('slug', 'home')->firstOrFail();
        $sectionCount = $home->sections()->count();

        $this->seed(WebsiteSeeder::class);

        $this->assertSame($sectionCount, $home->fresh()->sections()->count());
        $this->assertGreaterThanOrEqual(18, $sectionCount);
        $this->assertDatabaseHas('website_sections', ['website_page_id' => $home->id, 'type' => 'announcement']);
        $this->assertDatabaseHas('website_sections', ['website_page_id' => $home->id, 'type' => 'pricing']);
        $this->assertTrue(WebsiteNavigationItem::where('menu_group', 'header')->where('label', 'Features')->exists());
        $this->assertSame(['Features', 'Pricing', 'Contact', 'Blog', 'Resources'], WebsiteNavigationItem::where('menu_group', 'header')->orderBy('sort_order')->pluck('label')->all());
        $this->assertTrue(WebsitePage::where('slug', 'resources')->where('status', 'published')->exists());
        $this->assertTrue(WebsiteFooterLink::where('group', 'Legal')->where('label', 'Privacy')->exists());
        $this->assertSame('#059669', data_get(WebsiteSetting::where('group', 'theme')->where('key', 'accent_color')->firstOrFail()->value, 'value'));
        $this->assertCount(3, BlogPost::where('status', 'published')->get());
        $this->assertGreaterThanOrEqual(4, WebsitePage::where('slug', 'contact')->firstOrFail()->sections()->count());
        $this->assertGreaterThanOrEqual(6, WebsitePage::where('slug', 'security')->firstOrFail()->sections()->count());

        $response = $this->get('/')->assertOk()->assertSee('Bring every customer conversation into one clear support operation.')->assertSee('/branding/logo/light_logo.png', false);
        $this->assertStringNotContainsString('AI-assisted support', $response->getContent());
        $this->get('/contact')->assertOk()->assertSee('Start with the right conversation')->assertDontSee('@include', false);
        $this->get('/security')->assertOk()->assertSee('A practical access-control lifecycle');
        $this->get('/blog')->assertOk()->assertSee('Designing a clear support operation')->assertSee('Practical SLA and escalation workflows');
    }

    public function test_seeder_never_overwrites_an_existing_custom_homepage(): void
    {
        $page = WebsitePage::create(['title' => 'Custom Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()]);
        $page->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Customer-authored headline']]);

        $this->seed(WebsiteSeeder::class);

        $this->assertSame(1, $page->fresh()->sections()->count());
        $this->get('/')->assertOk()->assertSee('Customer-authored headline');
    }

    public function test_seeder_enriches_recognized_supporting_pages_without_overwriting_custom_pages(): void
    {
        $custom = WebsitePage::create(['title' => 'Custom Features', 'slug' => 'features', 'status' => 'published', 'published_at' => now()]);
        $custom->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Our custom feature story']]);

        $this->seed(WebsiteSeeder::class);

        $this->assertSame(1, $custom->fresh()->sections()->count());
        $this->get('/features')->assertOk()->assertSee('Our custom feature story')->assertDontSee('A complete support operating system');
    }

    public function test_blog_uses_the_shared_public_navigation_branding_and_footer(): void
    {
        $this->seed(WebsiteSeeder::class);

        $this->get('/blog')
            ->assertOk()
            ->assertSee('Practical ideas for clearer support operations.')
            ->assertSee('Features')
            ->assertSee('Start free')
            ->assertSee('/branding/logo/light_logo.png', false)
            ->assertSee('All rights reserved.');
    }

    public function test_seeder_replaces_only_the_known_misleading_legacy_starter(): void
    {
        $page = WebsitePage::create(['title' => 'PromptBot', 'slug' => 'home', 'status' => 'published', 'published_at' => now(), 'seo' => ['title' => 'PromptBot — AI support workspaces for growing teams']]);
        $page->sections()->create(['type' => 'hero', 'sort_order' => 0, 'content' => ['heading' => 'Resolve more conversations with one intelligent support platform.']]);

        $this->seed(WebsiteSeeder::class);

        $page->refresh();
        $this->assertGreaterThanOrEqual(18, $page->sections()->count());
        $this->assertSame('PromptBot | Omnichannel Customer Support & Helpdesk Platform', data_get($page->seo, 'title'));
        $this->get('/')->assertOk()->assertDontSee('AI support workspaces')->assertSee('Bring every customer conversation into one clear support operation.');
    }

    public function test_hidden_sections_are_omitted_and_order_is_respected(): void
    {
        $page = WebsitePage::create(['title' => 'Home', 'slug' => 'home', 'status' => 'published', 'published_at' => now()]);
        $page->sections()->create(['type' => 'rich_text', 'sort_order' => 2, 'content' => ['html' => '<p>Second visible</p>']]);
        $page->sections()->create(['type' => 'rich_text', 'sort_order' => 1, 'content' => ['html' => '<p>First visible</p>']]);
        $page->sections()->create(['type' => 'rich_text', 'sort_order' => 0, 'content' => ['html' => '<p>Hidden copy</p>'], 'is_hidden' => true]);

        $content = $this->get('/')->assertOk()->assertDontSee('Hidden copy')->getContent();
        $this->assertLessThan(strpos($content, 'Second visible'), strpos($content, 'First visible'));
    }
}
