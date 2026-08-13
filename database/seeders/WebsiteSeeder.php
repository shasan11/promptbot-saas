<?php

namespace Database\Seeders;

use App\Models\WebsitePage;
use App\Models\WebsiteForm;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $contactFields = [
                ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
                ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
        ];
        foreach ([
            'general-contact' => 'General contact',
            'contact-sales' => 'Contact sales',
            'request-demo' => 'Request a demo',
        ] as $slug => $name) WebsiteForm::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true, 'fields' => $contactFields]);
        WebsiteForm::firstOrCreate(['slug' => 'newsletter'], ['name' => 'Newsletter signup', 'is_active' => true, 'fields' => [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ]]);
        $page = WebsitePage::firstOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'PromptBot',
                'status' => 'published',
                'page_type' => 'home',
                'template' => 'default',
                'robots_index' => true,
                'robots_follow' => true,
                'seo' => [
                    'title' => 'PromptBot — AI support workspaces for growing teams',
                    'description' => 'Launch secure, multi-workspace AI customer support with centralized billing and administration.',
                ],
                'published_at' => now(),
            ]
        );

        if ($page->sections()->exists()) {
            return;
        }

        $sections = [
            ['hero', [
                'eyebrow' => 'AI support, under your control',
                'heading' => 'Resolve more conversations with one intelligent support platform.',
                'description' => 'Create dedicated workspaces for every brand, manage your team centrally, and keep billing simple as you grow.',
                'primary_label' => 'Start free trial', 'primary_url' => '/account/register',
                'secondary_label' => 'Book demo', 'secondary_url' => '/#request-demo',
            ]],
            ['feature_grid', [
                'heading' => 'Everything your support operation needs',
                'description' => 'Built for secure ownership, clear billing, and fast day-to-day work.',
                'items' => [
                    ['title' => 'Multiple workspaces', 'description' => 'Run separate brands and teams from one customer account.'],
                    ['title' => 'AI-assisted support', 'description' => 'Connect knowledge, automate replies, and keep agents in control.'],
                    ['title' => 'Central billing', 'description' => 'See subscriptions, invoices, and payments without switching products.'],
                ],
            ]],
            ['image_text', ['heading' => 'AI that works from your knowledge', 'description' => 'Connect trusted sources, keep content fresh, and help agents answer with consistent context.', 'image_position' => 'right']],
            ['how_it_works', ['heading' => 'How PromptBot works', 'steps' => [
                ['title' => 'Create a workspace', 'description' => 'Separate each brand, team, and data boundary.'],
                ['title' => 'Connect your knowledge', 'description' => 'Bring policies, help content, and operational context together.'],
                ['title' => 'Support customers', 'description' => 'Use AI assistance, automation, inbox, and helpdesk workflows.'],
            ]]],
            ['pricing', ['heading' => 'Start with the plan that fits', 'description' => 'Transparent plans with a guided trial.', 'interval' => 'monthly']],
            ['faq', [
                'heading' => 'Frequently asked questions',
                'items' => [
                    ['question' => 'Can one account own multiple workspaces?', 'answer' => 'Yes. Each workspace stays isolated while account owners manage access and billing centrally.'],
                    ['question' => 'Can I change plans later?', 'answer' => 'Yes. Eligible account members can schedule or apply plan changes from the customer portal.'],
                ],
            ]],
            ['testimonials', ['heading' => 'Built for support teams that care about control', 'items' => [
                ['quote' => 'PromptBot gives our team one clear place to run support across brands.', 'name' => 'Customer Operations', 'role' => 'Support lead', 'company' => 'Growing SaaS team'],
            ]]],
            ['cta', [
                'heading' => 'Ready to build a better support experience?',
                'description' => 'Create your customer account and launch your first workspace.',
                'primary_label' => 'Start free trial', 'primary_url' => '/account/register',
                'secondary_label' => 'Sign in', 'secondary_url' => '/account/login',
                'background' => 'brand',
            ]],
            ['contact_form', ['heading' => 'Book a PromptBot demo', 'description' => 'Tell us what you are building and we will get back to you.', 'form_slug' => 'request-demo']],
        ];

        foreach ($sections as $sortOrder => [$type, $content]) {
            $page->sections()->create([
                'type' => $type,
                'sort_order' => $sortOrder,
                'content' => $content,
                'is_hidden' => false,
            ]);
        }
    }
}
