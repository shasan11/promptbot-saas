<?php

namespace Tests\Feature\Platform;

use App\Models\WebsitePage;
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
}
