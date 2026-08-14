<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Models\WebsiteFooterLink;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithPlatformPermissions;
use Tests\TestCase;

class WebsiteControllerTest extends TestCase
{
    use InteractsWithPlatformPermissions, RefreshDatabase;

    public function test_admin_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->centralAdminWithPermissions([]), 'central')
            ->get(route('superadmin.website.index'))
            ->assertForbidden();
    }

    public function test_admin_with_view_permission_can_view_the_dashboard(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['website.view']), 'central')
            ->get(route('superadmin.website.index'))
            ->assertOk();
    }

    public function test_view_permission_does_not_grant_page_creation(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['website.view']), 'central')
            ->get(route('superadmin.website.pages.create'))
            ->assertForbidden();
    }

    public function test_admin_can_create_a_page_and_save_sections(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage', 'website.view']);

        $this->actingAs($admin, 'central')->post(route('superadmin.website.pages.store'), [
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
            'seo_title' => 'Welcome',
        ])->assertRedirect();

        $page = WebsitePage::query()->where('slug', 'home')->firstOrFail();
        $this->assertSame('published', $page->status);
        $this->assertNotNull($page->published_at);

        $this->actingAs($admin, 'central')->put(route('superadmin.website.pages.sections', $page), [
            'sections' => [
                ['type' => 'hero', 'content' => ['heading' => 'Welcome', 'unexpected_key' => 'dropped']],
                ['type' => 'rich_text', 'content' => ['html' => '<p>Body</p>']],
            ],
        ])->assertRedirect();

        $page->refresh();
        $this->assertCount(2, $page->sections);
        $this->assertSame('hero', $page->sections->first()->type);
        $this->assertArrayNotHasKey('unexpected_key', $page->sections->first()->content);
        $this->assertSame('Welcome', $page->sections->first()->content['heading']);
    }

    public function test_admin_can_manage_navigation_items(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);

        $this->actingAs($admin, 'central')->post(route('superadmin.website.navigation.store'), [
            'label' => 'Pricing',
            'url' => '/pricing',
        ])->assertRedirect();

        $item = WebsiteNavigationItem::query()->where('label', 'Pricing')->firstOrFail();

        $this->actingAs($admin, 'central')->put(route('superadmin.website.navigation.update', $item), [
            'label' => 'Pricing plans',
            'url' => '/pricing',
            'is_active' => true,
        ])->assertRedirect();

        $this->assertSame('Pricing plans', $item->fresh()->label);

        $this->actingAs($admin, 'central')->delete(route('superadmin.website.navigation.destroy', $item))->assertRedirect();
        $this->assertDatabaseMissing('website_navigation_items', ['id' => $item->id]);
    }

    public function test_admin_can_manage_footer_links(): void
    {
        $admin = $this->centralAdminWithPermissions(['website.manage']);

        $this->actingAs($admin, 'central')->post(route('superadmin.website.footer-links.store'), [
            'label' => 'Privacy',
            'url' => '/privacy',
            'group' => 'Legal',
        ])->assertRedirect();

        $this->assertDatabaseHas('website_footer_links', ['label' => 'Privacy', 'group' => 'Legal']);

        $link = WebsiteFooterLink::query()->where('label', 'Privacy')->firstOrFail();
        $this->actingAs($admin, 'central')->delete(route('superadmin.website.footer-links.destroy', $link))->assertRedirect();
        $this->assertDatabaseMissing('website_footer_links', ['id' => $link->id]);
    }

    public function test_admin_can_update_website_settings(): void
    {
        $this->actingAs($this->centralAdminWithPermissions(['website.manage']), 'central')
            ->put(route('superadmin.website.settings.update'), [
                'site_name' => 'Acme',
                'contact_email' => 'hello@acme.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('website_settings', ['group' => 'general', 'key' => 'site_name']);
    }

    public function test_admin_can_upload_website_logos_directly(): void
    {
        Storage::fake('public');

        $this->actingAs($this->centralAdminWithPermissions(['website.manage']), 'central')
            ->put(route('superadmin.website.settings.update'), [
                'site_name' => 'Acme',
                'logo_file' => UploadedFile::fake()->image('logo.png', 480, 120),
                'logo_dark_file' => UploadedFile::fake()->image('logo-dark.webp', 480, 120),
                'favicon_file' => UploadedFile::fake()->image('favicon.png', 64, 64),
            ])->assertRedirect();

        foreach (['logo_url', 'logo_dark_url', 'favicon_url'] as $key) {
            $url = data_get(WebsiteSetting::where('key', $key)->firstOrFail()->value, 'value');
            $this->assertStringContainsString('/storage/website/branding/', $url);
        }
    }
}
