<?php

namespace App\Http\Controllers;

use App\Models\WebsiteFooterLink;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicWebsiteController extends Controller
{
    /**
     * Renders a published WebsitePage. The root URL falls back to the
     * default Laravel welcome screen when no "home" page has been published
     * yet, so a fresh install still has a landing page before an admin has
     * touched the CMS.
     */
    public function show(Request $request, string $slug = 'home')
    {
        $page = WebsitePage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('sections')
            ->first();

        if (! $page) {
            if ($slug !== 'home') {
                abort(404);
            }

            return Inertia::render('Welcome', [
                'canLogin' => Route::has('login'),
                'canRegister' => Route::has('register'),
                'laravelVersion' => Application::VERSION,
                'phpVersion' => PHP_VERSION,
            ]);
        }

        $settings = WebsiteSetting::query()->where('group', 'general')->get()
            ->mapWithKeys(fn (WebsiteSetting $setting) => [$setting->key => data_get($setting->value, 'value')]);

        return view('website.page', [
            'page' => $page,
            'navigation' => WebsiteNavigationItem::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'footerLinks' => WebsiteFooterLink::query()->orderBy('sort_order')->get()->groupBy(fn (WebsiteFooterLink $link) => $link->group ?: 'General'),
            'settings' => $settings,
        ]);
    }
}
