<?php

namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\WebsiteFooterLink;
use App\Models\WebsiteForm;
use App\Models\WebsiteNavigationItem;
use App\Models\WebsitePage;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

class WebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedForms();
        $pages = $this->seedPages();
        $this->seedBlogPosts();
        $this->seedNavigation($pages);
        $this->seedFooter();
        $this->seedSettings();
    }

    private function seedForms(): void
    {
        $fields = [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Work email', 'type' => 'email', 'required' => true],
            ['name' => 'company', 'label' => 'Company', 'type' => 'text', 'required' => false],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'tel', 'required' => false],
            ['name' => 'message', 'label' => 'How can we help?', 'type' => 'textarea', 'required' => true],
        ];
        foreach (['general-contact' => 'General Contact', 'contact-sales' => 'Contact Sales', 'request-demo' => 'Request Demo'] as $slug => $name) {
            WebsiteForm::firstOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true, 'fields' => $fields]);
        }
        WebsiteForm::firstOrCreate(['slug' => 'newsletter'], ['name' => 'Newsletter', 'is_active' => true, 'fields' => [
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
        ]]);
    }

    private function seedPages(): array
    {
        $home = WebsitePage::firstOrCreate(['slug' => 'home'], [
            'title' => 'PromptBot', 'status' => 'published', 'page_type' => 'home', 'template' => 'default',
            'robots_index' => true, 'robots_follow' => true, 'published_at' => now(),
            'seo' => ['title' => 'PromptBot | Omnichannel Customer Support & Helpdesk Platform', 'description' => 'Bring customer conversations, tickets, tasks, SLAs, workflows, help-center content, and support operations together with PromptBot.'],
            'schema_json' => ['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'PromptBot', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => 'A self-hosted, multi-tenant omnichannel customer support and helpdesk platform.'],
        ]);

        if (data_get($home->seo, 'title') === 'PromptBot — Omnichannel Customer Support & Helpdesk Platform') {
            $seo = $home->seo ?? [];
            $seo['title'] = 'PromptBot | Omnichannel Customer Support & Helpdesk Platform';
            $home->update(['seo' => $seo]);
        }

        $legacyHero = $home->sections()->where('type', 'hero')->first();
        $isLegacyStarter = data_get($legacyHero?->content, 'heading') === 'Resolve more conversations with one intelligent support platform.';
        if ($isLegacyStarter) {
            $home->sections()->delete();
            $home->update([
                'seo' => ['title' => 'PromptBot | Omnichannel Customer Support & Helpdesk Platform', 'description' => 'Bring customer conversations, tickets, tasks, SLAs, workflows, help-center content, and support operations together with PromptBot.'],
                'schema_json' => ['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'PromptBot', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'description' => 'A self-hosted, multi-tenant omnichannel customer support and helpdesk platform.'],
            ]);
        }

        if (! $home->sections()->exists()) {
            $this->sections($home, $this->homeSections());
        }

        $definitions = [
            'features' => ['Features', 'Explore the PromptBot platform', 'Bring conversations, customers, tickets, tasks, SLAs, forms, knowledge, feedback, and reporting into one structured support operation.', [
                ['feature_grid', ['heading' => 'A complete support operating system', 'description' => 'Each capability is designed to share customer and operational context.', 'items' => $this->features()]],
                ['feature_showcase', ['heading' => 'Move from conversation to resolution without losing context', 'description' => 'The inbox, customer record, tickets, tasks, and SLA state stay close together so ownership and next steps remain visible.', 'items' => [['title' => 'Receive and organize', 'description' => 'Bring supported customer channels into a shared operating view.'], ['title' => 'Structure the work', 'description' => 'Use assignments, tickets, priorities, tasks, and service expectations.'], ['title' => 'Review and improve', 'description' => 'Use persisted operational activity, reporting, quality, and feedback workflows.']]]],
                ['feature_list', ['heading' => 'Control without opaque automation', 'description' => 'Standardize repeatable work through explicit rules and keep important support decisions understandable.', 'items' => [['title' => 'Deterministic workflows', 'description' => 'Define known triggers, conditions, and actions for predictable processes.'], ['title' => 'Workspace boundaries', 'description' => 'Keep brands or operations separated while the customer account manages ownership centrally.'], ['title' => 'Self-hosted deployment', 'description' => 'Operate the platform within an environment controlled for your deployment needs.']]]],
                ['cta', ['heading' => 'Build a clearer support operation', 'description' => 'Create your customer account and launch a workspace when you are ready.', 'primary_label' => 'Start free', 'primary_url' => '/account/register', 'secondary_label' => 'View pricing', 'secondary_url' => '/pricing']],
            ]],
            'pricing' => ['Pricing', 'Pricing that grows with your support operation', 'Choose from the active plans configured by your platform administrator. Plan availability and prices below always come from the live billing catalog.', [
                ['feature_grid', ['heading' => 'Every plan starts with the same operating foundation', 'description' => 'Available limits and commercial terms come from the live catalog; the core workflow remains connected.', 'items' => [['title' => 'Customer account portal', 'description' => 'Manage eligible workspaces, subscriptions, invoices, payment details, members, and account support.'], ['title' => 'Workspace separation', 'description' => 'Operate each support environment within its own tenant and staff identity boundary.'], ['title' => 'Visible plan context', 'description' => 'Carry the selected public plan and billing interval into registration and workspace provisioning.']]]],
                ['pricing', ['heading' => 'Choose the right foundation', 'description' => 'Switch between monthly and yearly billing. You can review the selected plan before provisioning a workspace.', 'data_source' => 'live_plans', 'show_billing_toggle' => true, 'default_interval' => 'monthly', 'cta_label' => 'Choose plan']],
                ['comparison_table', ['heading' => 'Understand what you are choosing', 'columns' => ['Plan catalog', 'Customer portal'], 'rows' => [['feature' => 'Availability', 'plan_catalog' => 'Defines active public plans', 'customer_portal' => 'Shows eligible services'], ['feature' => 'Prices', 'plan_catalog' => 'Stores monthly and yearly amounts', 'customer_portal' => 'Displays the selected interval'], ['feature' => 'Limits', 'plan_catalog' => 'Defines configured plan limits', 'customer_portal' => 'Applies limits during service workflows'], ['feature' => 'Changes', 'plan_catalog' => 'Defines allowed commercial options', 'customer_portal' => 'Lets eligible members manage supported changes']]]],
                ['faq', ['heading' => 'Pricing questions', 'description' => 'Clear answers before you choose a public plan.', 'items' => [['question' => 'Where do the displayed prices come from?', 'answer' => 'The pricing section reads active public plans from the live billing catalog configured by the platform administrator.'], ['question' => 'Can I change plans later?', 'answer' => 'Eligible account members can manage supported plan changes from the customer portal when those workflows are enabled.'], ['question' => 'Does one account support multiple workspaces?', 'answer' => 'Yes. Workspace availability and limits depend on platform policy and the selected plans.'], ['question' => 'What happens to my selection during signup?', 'answer' => 'The selected plan and monthly or yearly interval are preserved through registration and reviewed during account setup.'], ['question' => 'Are taxes or payment methods universal?', 'answer' => 'Billing behavior, gateways, taxes, currencies, and payment-method availability depend on the configured deployment.']]]],
                ['cta', ['heading' => 'Choose a plan when you are ready', 'description' => 'Your selected plan and billing interval follow you into the minimal account setup flow.', 'primary_label' => 'Create account', 'primary_url' => '/account/register', 'secondary_label' => 'Contact us', 'secondary_url' => '/contact']],
            ]],
            'about' => ['About', 'Support operations deserve clarity', 'PromptBot is built for teams that need connected customer-service workflows, strong workspace boundaries, and control over their deployment.', [
                ['feature_list', ['heading' => 'Built around accountable service', 'description' => 'PromptBot focuses on practical customer-support operations rather than opaque decision-making.', 'items' => [['title' => 'Operational clarity', 'description' => 'Keep conversations, tickets, tasks, SLAs, and customer context connected.'], ['title' => 'Understandable automation', 'description' => 'Standardize repeatable workflows through explicit deterministic rules.'], ['title' => 'Deployment control', 'description' => 'Run a self-hosted platform with multi-tenant workspace architecture.']]]],
                ['feature_grid', ['heading' => 'Principles behind the product', 'description' => 'Product decisions center on visible ownership, truthful capability claims, and clear identity boundaries.', 'items' => [['title' => 'Connected context', 'description' => 'Service work is easier to understand when communication and operations share context.'], ['title' => 'Explicit control', 'description' => 'Permissions, policies, and workflows should make important behavior reviewable.'], ['title' => 'Honest software', 'description' => 'PromptBot describes what the current product does without inventing AI or compliance claims.']]]],
                ['how_it_works', ['heading' => 'A platform for the full operating loop', 'steps' => [['title' => 'Listen', 'description' => 'Receive customer conversations and structured form submissions.'], ['title' => 'Organize', 'description' => 'Connect customers, tickets, tasks, priorities, and ownership.'], ['title' => 'Deliver', 'description' => 'Work against clear service expectations and explicit workflows.'], ['title' => 'Improve', 'description' => 'Review activity, reports, feedback, and quality processes.']]]],
                ['cta', ['heading' => 'See how PromptBot fits your team', 'description' => 'Explore the complete product or start with an available public plan.', 'primary_label' => 'Explore features', 'primary_url' => '/features', 'secondary_label' => 'View pricing', 'secondary_url' => '/pricing']],
            ]],
            'contact' => ['Contact', 'Let’s talk about your support operation', 'Tell us how your team works today and what you need to improve.', [
                ['feature_grid', ['heading' => 'Start with the right conversation', 'description' => 'Choose the route that best matches what you need from PromptBot.', 'items' => [['title' => 'Product questions', 'description' => 'Ask about inbox, tickets, tasks, SLAs, workflows, customer accounts, or workspace boundaries.'], ['title' => 'Deployment planning', 'description' => 'Discuss self-hosted deployment expectations and the environment your team manages.'], ['title' => 'Account support', 'description' => 'Existing customers can use the customer portal to open and track account-level support cases.']]]],
                ['contact_form', ['heading' => 'Tell us what you are building', 'description' => 'Include your team size, current workflow, workspace needs, and the outcome you want to improve.', 'form_slug' => 'general-contact']],
                ['faq', ['heading' => 'Before you get in touch', 'items' => [['question' => 'What information should I include?', 'answer' => 'Share your current support channels, number of teams or brands, workflow challenges, deployment questions, and desired outcome.'], ['question' => 'Where do existing customers request help?', 'answer' => 'Sign in to the customer portal to create and follow account-level support cases.'], ['question' => 'Can I ask about self-hosting?', 'answer' => 'Yes. Describe the environment you expect to manage and any operational requirements you need to evaluate.']]]],
            ]],
            'privacy' => ['Privacy Policy', 'Privacy policy starter', 'This editable starter helps administrators prepare a complete policy structure. It is not legal advice and must be reviewed for your deployment and jurisdiction.', [['rich_text', ['html' => '<h2>Before publishing</h2><p>Replace this administrative starter with a policy reviewed by qualified counsel for your organization, deployment, customers, and jurisdiction.</p><h2>Information to describe</h2><p>Document account and profile data, customer-support content, workspace records, billing details, form submissions, technical logs, device and session information, and any cookies or analytics used by your deployment.</p><h2>Purpose and lawful basis</h2><p>Explain why each category is processed, the applicable lawful basis, and whether providing it is required to deliver the service.</p><h2>Storage, retention, and deletion</h2><p>State where data is hosted, how long each category is retained, backup practices, deletion workflows, and what may be preserved for legal or security reasons.</p><h2>Sharing and service providers</h2><p>Identify processors and service providers, cross-border transfers, contractual safeguards, and circumstances where information may be disclosed.</p><h2>Security and access</h2><p>Describe access controls, authentication options, workspace boundaries, session management, monitoring, and incident-response contacts without claiming certifications you do not hold.</p><h2>Individual rights and contact</h2><p>Explain how people may request access, correction, deletion, restriction, portability, or objection, and provide a monitored privacy contact.</p>']]]],
            'terms' => ['Terms of Service', 'Terms of service starter', 'This editable starter outlines the areas a complete service agreement normally addresses. It is not legal advice and requires qualified review.', [['rich_text', ['html' => '<h2>Before publishing</h2><p>Replace this administrative starter with terms reviewed for your commercial model, deployment, customers, and governing jurisdiction.</p><h2>Service and accounts</h2><p>Define PromptBot, customer accounts, workspaces, authorized users, eligibility, account security responsibilities, and the authority required to accept the agreement.</p><h2>Acceptable use</h2><p>Describe prohibited content and conduct, misuse of communication channels, attempts to bypass access controls, unlawful processing, and interference with service operation.</p><h2>Plans, billing, and taxes</h2><p>Explain plan scope, usage limits, billing intervals, renewal, payment obligations, applicable taxes, plan changes, refunds, and cancellation procedures.</p><h2>Customer content and privacy</h2><p>Clarify ownership, processing instructions, required permissions, data exports, retention, deletion, and responsibility for notices to end users.</p><h2>Availability and support</h2><p>State support channels, maintenance practices, service targets if offered, exclusions, and how material operational incidents are communicated.</p><h2>Intellectual property</h2><p>Address ownership of the software, permitted use, feedback, third-party components, documentation, branding, and restrictions on redistribution.</p><h2>Suspension, termination, and liability</h2><p>Define suspension and termination events, post-termination access, warranty disclaimers, liability limits, indemnities, dispute procedures, and governing law.</p>']]]],
            'security' => ['Security', 'Security through clear boundaries and control', 'PromptBot separates platform operators, customer account identities, and workspace users while supporting practical access and session controls.', [
                ['feature_grid', ['heading' => 'Controls built into the architecture', 'description' => 'Practical controls support safer operation without making unsupported certification claims.', 'items' => [['title' => 'Identity separation', 'description' => 'Platform, portal, and tenant identities use separate authentication guards and data boundaries.'], ['title' => 'Roles and permissions', 'description' => 'Control access through account membership policies and role-based authorization.'], ['title' => 'Two-factor authentication', 'description' => 'Customer and platform identities can use a local second authentication factor.'], ['title' => 'Session visibility', 'description' => 'Portal sessions and login activity support review and revocation.'], ['title' => 'Tenant isolation', 'description' => 'Workspace architecture keeps tenant data and staff identities separated.'], ['title' => 'Self-hosted control', 'description' => 'Operate PromptBot in an environment managed for your deployment requirements.']]]],
                ['feature_list', ['heading' => 'Three identity surfaces, three clear purposes', 'description' => 'Authentication boundaries reduce accidental privilege crossover.', 'items' => [['title' => 'Platform operators', 'description' => 'Superadmins manage platform settings, customers, billing policy, and operations through the central guard.'], ['title' => 'Customer account members', 'description' => 'Portal users manage commercial accounts, workspaces, billing, members, support, profile security, and sessions.'], ['title' => 'Workspace staff', 'description' => 'Tenant users enter through their workspace domain and remain separate from account and platform identities.']]]],
                ['how_it_works', ['heading' => 'A practical access-control lifecycle', 'steps' => [['title' => 'Verify identity', 'description' => 'Use the correct guard, verified email policy, password or approved Google identity, and optional 2FA.'], ['title' => 'Authorize action', 'description' => 'Apply membership, role, permission, account, and workspace policies to sensitive operations.'], ['title' => 'Track access', 'description' => 'Record portal login activity and active sessions for review.'], ['title' => 'Revoke when needed', 'description' => 'End sessions, remove memberships, suspend identities, or disable access according to policy.']]]],
                ['faq', ['heading' => 'Security questions', 'items' => [['question' => 'Does PromptBot isolate workspaces?', 'answer' => 'Yes. The tenant architecture separates workspace data and staff identities, while customer-account membership is managed centrally.'], ['question' => 'Can customers use two-factor authentication?', 'answer' => 'Yes. Portal identities support a local second factor, including after Google authentication when 2FA is enabled.'], ['question' => 'Does this page claim a compliance certification?', 'answer' => 'No. Deployment operators must assess their own configuration, controls, obligations, and any certification requirements.'], ['question' => 'Can active sessions be reviewed?', 'answer' => 'Portal session visibility supports reviewing and revoking customer-account sessions.']]]],
                ['cta', ['heading' => 'Evaluate PromptBot for your environment', 'description' => 'Bring your deployment, identity, tenant-boundary, and access-control questions to the team.', 'primary_label' => 'Contact us', 'primary_url' => '/contact', 'secondary_label' => 'Explore features', 'secondary_url' => '/features']],
            ]],
            'resources' => ['Resources', 'Guides for clearer support operations', 'Explore practical product guidance, workflow patterns, and ideas for building a more accountable customer-service operation.', [
                ['resources', ['eyebrow' => 'Latest guidance', 'heading' => 'Learn from practical support workflows', 'description' => 'Browse articles written for teams improving ownership, service levels, and customer context.', 'limit' => 6, 'button_label' => 'Browse the blog', 'button_url' => '/blog']],
                ['feature_grid', ['heading' => 'Useful starting points', 'description' => 'Move from ideas to a clearer operating model.', 'items' => [['title' => 'Workflow design', 'description' => 'Build predictable routing, ownership, escalation, and follow-up patterns.'], ['title' => 'Service operations', 'description' => 'Connect inboxes, tickets, tasks, customers, and service expectations.'], ['title' => 'Platform setup', 'description' => 'Plan customer accounts, workspaces, permissions, and self-hosted operations.']]]],
                ['newsletter', ['heading' => 'Get new operating guides', 'description' => 'Receive practical PromptBot product and support-operations updates.']],
            ]],
        ];

        $pages = ['home' => $home];
        foreach ($definitions as $slug => [$title, $heading, $description, $sections]) {
            $page = WebsitePage::firstOrCreate(['slug' => $slug], ['title' => $title, 'status' => 'published', 'published_at' => now(), 'robots_index' => true, 'robots_follow' => true, 'seo' => ['title' => $heading, 'description' => $description]]);
            $starterHero = $page->sections()->where('type', 'hero')->first();
            $legacyStarterSectionCounts = ['features' => 5, 'pricing' => 4, 'about' => 5, 'contact' => 2, 'privacy' => 2, 'terms' => 2, 'security' => 2];
            $isRecognizedStarter = data_get($page->seo, 'starter_revision') !== 2 && data_get($starterHero?->content, 'heading') === $heading && $page->sections()->count() === $legacyStarterSectionCounts[$slug];
            $isManagedStarter = $page->wasRecentlyCreated || $isRecognizedStarter;
            if ($isRecognizedStarter) {
                $page->sections()->delete();
            }
            if (! $page->sections()->exists()) {
                $this->sections($page, [['hero', ['eyebrow' => 'PromptBot', 'heading' => $heading, 'description' => $description, 'alignment' => 'center']], ...$sections]);
            }
            if ($isManagedStarter) {
                $page->update(['seo' => [...($page->seo ?? []), 'starter_revision' => 2]]);
            }
            $pages[$slug] = $page;
        }

        return $pages;
    }

    private function homeSections(): array
    {
        return [
            ['announcement', ['message' => 'Built for teams that want customer support without operational chaos.', 'link_label' => 'Explore the platform', 'link_url' => '/features', 'variant' => 'brand']],
            ['hero', ['eyebrow' => 'One workspace for modern customer support', 'heading' => 'Bring every customer conversation into one clear support operation.', 'description' => 'PromptBot gives growing teams one place to manage conversations, customers, tickets, tasks, SLAs, workflows, knowledge, and service operations across channels.', 'primary_label' => 'Start free', 'primary_url' => '/account/register', 'secondary_label' => 'Explore the platform', 'secondary_url' => '/features', 'alignment' => 'left', 'background' => 'light']],
            ['logo_cloud', ['heading' => 'Built for operational clarity', 'items' => [['name' => 'Multi-workspace architecture'], ['name' => 'Role-based access'], ['name' => 'Customer portal'], ['name' => 'Structured support operations'], ['name' => 'Self-hosted deployment']]]],
            ['image_text', ['heading' => 'Support gets messy when the tools don’t talk to each other.', 'description' => 'Channels fragment customer history, follow-ups get missed, and service expectations become hard to track. PromptBot connects communication with tickets, tasks, SLAs, customer context, and reporting.', 'button_label' => 'See all features', 'button_url' => '/features', 'image_position' => 'right']],
            ['feature_grid', ['heading' => 'Everything your support operation needs', 'description' => 'Connected capabilities create a clearer day-to-day operating view without inventing AI decisions.', 'items' => $this->features()]],
            ['feature_showcase', ['heading' => 'Every conversation. One operating view.', 'description' => 'Bring customer communication into a unified inbox while keeping customer context, ownership, and service work close at hand.', 'items' => [['title' => 'Shared visibility', 'description' => 'Organize conversations so work is visible to the right people.'], ['title' => 'Customer context', 'description' => 'Keep communication connected to the customer history behind it.'], ['title' => 'Structured ownership', 'description' => 'Use assignments and statuses to make next steps explicit.']]]],
            ['image_text', ['heading' => 'Turn conversations into accountable work.', 'description' => 'Create structured tickets, set priorities, assign ownership, track SLA expectations, and keep operational follow-ups connected through tasks.', 'image_position' => 'left']],
            ['feature_list', ['heading' => 'Automate the predictable. Keep control of the important.', 'description' => 'PromptBot uses deterministic automation: explicit rules that standardize repeatable workflows while keeping behavior understandable.', 'items' => [['title' => 'Clear triggers', 'description' => 'Define when a predictable workflow should run.'], ['title' => 'Explicit actions', 'description' => 'Choose the operational changes a rule may perform.'], ['title' => 'Understandable outcomes', 'description' => 'Keep critical support behavior reviewable instead of relying on opaque AI decisions.']]]],
            ['feature_showcase', ['heading' => 'One customer account. Multiple support workspaces.', 'description' => 'Operate separate brands or support environments with workspace boundaries while managing commercial ownership, members, and billing centrally.', 'items' => [['title' => 'Customer account', 'description' => 'The central home for ownership, billing, members, and support.'], ['title' => 'Workspace A · Workspace B · Workspace C', 'description' => 'Keep operations separated for each team or brand.'], ['title' => 'Central account control', 'description' => 'Switch accounts and manage services without merging workspace identities.']]]],
            ['feature_grid', ['heading' => 'A better experience outside the helpdesk, too.', 'description' => 'The commercial customer portal gives account members one place to manage PromptBot services.', 'items' => [['title' => 'Workspaces', 'description' => 'Create and review the support workspaces owned by an account.'], ['title' => 'Billing', 'description' => 'Review subscriptions, invoices, payments, methods, and billing details.'], ['title' => 'Members', 'description' => 'Invite account members and control commercial account roles.'], ['title' => 'Support', 'description' => 'Open and follow account-level support cases.'], ['title' => 'Profile & security', 'description' => 'Manage identity details, password, 2FA, sessions, and notifications.'], ['title' => 'Account switching', 'description' => 'Move between eligible customer accounts without another sign-in.']]]],
            ['image_text', ['heading' => 'Your support platform. Your deployment.', 'description' => 'PromptBot is self-hosted, giving your organization control of the deployment environment while its tenant architecture maintains separate workspace boundaries.', 'image_position' => 'right']],
            ['feature_grid', ['heading' => 'Practical security and access controls', 'description' => 'Use verified platform capabilities without relying on unsubstantiated compliance claims.', 'items' => [['title' => 'Separate identities', 'description' => 'Platform, portal, and tenant users authenticate independently.'], ['title' => 'Roles and policies', 'description' => 'Account membership and permissions protect sensitive actions.'], ['title' => 'Email verification', 'description' => 'Require verified customer email ownership by platform policy.'], ['title' => 'Two-factor authentication', 'description' => 'Add a local second factor to customer and platform accounts.'], ['title' => 'Session controls', 'description' => 'Track portal sessions and revoke access when needed.'], ['title' => 'Tenant boundaries', 'description' => 'Keep workspace data and staff identities isolated.']]]],
            ['how_it_works', ['heading' => 'From account to better support in four steps', 'steps' => [['title' => 'Create your account', 'description' => 'Set up the commercial customer account that owns your services.'], ['title' => 'Launch a workspace', 'description' => 'Provision a workspace for a support operation or brand.'], ['title' => 'Configure your operation', 'description' => 'Set up channels, users, workflows, tickets, SLAs, forms, and resources.'], ['title' => 'Support and improve', 'description' => 'Handle work, measure service activity, gather feedback, and refine operations.']]]],
            ['feature_grid', ['heading' => 'Designed for real support operations', 'description' => 'PromptBot fits teams that need more structure without losing deployment control.', 'items' => [['title' => 'SaaS support teams', 'description' => 'Centralize conversations, tickets, customer context, and account workflows.'], ['title' => 'Multi-brand businesses', 'description' => 'Operate separate support workspaces while managing ownership centrally.'], ['title' => 'Growing service teams', 'description' => 'Introduce structure around assignments, SLAs, tasks, and reporting.'], ['title' => 'Service businesses', 'description' => 'Manage ongoing customer communication and operational follow-up.']]]],
            ['comparison_table', ['heading' => 'Why teams choose one connected operation', 'columns' => ['Scattered tools', 'PromptBot'], 'rows' => [['feature' => 'Customer communication', 'scattered_tools' => 'Disconnected inboxes', 'promptbot' => 'Unified support workflows'], ['feature' => 'Service work', 'scattered_tools' => 'Manual follow-up', 'promptbot' => 'Tickets, tasks, and automation'], ['feature' => 'Customer context', 'scattered_tools' => 'Fragmented records', 'promptbot' => 'Connected customer history'], ['feature' => 'Commercial management', 'scattered_tools' => 'Separate systems', 'promptbot' => 'Central customer account portal']]]],
            ['pricing', ['heading' => 'Pricing connected to the real product', 'description' => 'Choose from active public plans. Your selection follows you into customer registration.', 'data_source' => 'live_plans', 'show_billing_toggle' => true, 'default_interval' => 'monthly', 'cta_label' => 'Start free']],
            ['faq', ['heading' => 'Questions, answered clearly', 'description' => 'What to know before getting started.', 'items' => $this->faq()]],
            ['resources', ['eyebrow' => 'Resources', 'heading' => 'Build a stronger support operation', 'description' => 'Practical guidance from the PromptBot team.', 'limit' => 3, 'button_label' => 'View all resources', 'button_url' => '/blog']],
            ['cta', ['heading' => 'Your support operation deserves one clear system.', 'description' => 'Bring customers, conversations, tickets, tasks, workflows, SLAs, and service operations together with PromptBot.', 'primary_label' => 'Start free', 'primary_url' => '/account/register', 'secondary_label' => 'Talk to us', 'secondary_url' => '/contact']],
        ];
    }

    private function features(): array
    {
        return collect([
            'Unified Inbox' => 'Bring customer conversations into one organized operating view.', 'Customer Management' => 'Keep identity, contact history, context, and activity easier to understand.', 'Ticket Management' => 'Turn customer issues into structured, assignable, trackable work.', 'Tasks' => 'Keep operational follow-ups connected to the work that created them.', 'SLA Management' => 'Define and track service expectations without relying on spreadsheets.', 'Deterministic Automation' => 'Automate predictable workflows using explicit, understandable rules.', 'Forms' => 'Capture structured information and turn submissions into operational work.', 'Customer Portal' => 'Manage services, billing, members, and account-level support.', 'Help Center' => 'Publish support resources customers can access on their own.', 'CSAT' => 'Collect customer feedback after support interactions.', 'Reporting' => 'Understand workload, service activity, and team performance from persisted data.', 'Quality & Workforce' => 'Support quality reviews and workforce-management basics in the same ecosystem.',
        ])->map(fn ($description, $title) => ['title' => $title, 'description' => $description])->values()->all();
    }

    private function faq(): array
    {
        return [
            ['question' => 'What is PromptBot?', 'answer' => 'PromptBot is a self-hosted, multi-tenant omnichannel helpdesk for managing customer conversations and support operations.'],
            ['question' => 'Is PromptBot self-hosted?', 'answer' => 'Yes. PromptBot is designed to run in an environment managed for your deployment.'],
            ['question' => 'Can one account manage multiple workspaces?', 'answer' => 'Yes. One customer account can own multiple separately bounded support workspaces, subject to configured limits.'],
            ['question' => 'What is a workspace?', 'answer' => 'A workspace is a separate support operation or brand with its own tenant boundary and staff identities.'],
            ['question' => 'Can I invite other account members?', 'answer' => 'Yes, when member invitations are enabled. Roles and capabilities control which commercial account actions a member can perform.'],
            ['question' => 'Can I change my subscription later?', 'answer' => 'Supported plan changes and cancellation options are available to eligible members when enabled by platform policy.'],
            ['question' => 'Does PromptBot include customer support ticketing?', 'answer' => 'Yes. PromptBot includes conversations, tickets, tasks, SLA management, forms, customer context, and related service workflows.'],
            ['question' => 'Can customers access a portal?', 'answer' => 'PromptBot includes an account-level customer portal for services, billing, members, support, profile, security, and notifications.'],
            ['question' => 'Does PromptBot provide automation?', 'answer' => 'Yes. It provides deterministic automation using explicit rules for predictable workflows.'],
            ['question' => 'Does PromptBot currently use AI for support decisions?', 'answer' => 'No. This release does not generate replies, classify, summarize, route, analyze sentiment, or make support decisions with AI.'],
            ['question' => 'How do I get started?', 'answer' => 'Create a customer account, select an available plan, and launch your first support workspace.'],
        ];
    }

    private function sections(WebsitePage $page, array $sections): void
    {
        foreach ($sections as $order => [$type, $content]) {
            $page->sections()->create(['type' => $type, 'sort_order' => $order, 'content' => $content, 'is_hidden' => false]);
        }
    }

    private function seedBlogPosts(): void
    {
        $posts = [
            ['designing-a-clear-support-operation', 'Designing a clear support operation', 'A practical framework for connecting customer communication, ownership, tickets, tasks, SLAs, and review loops.', '<p>Customer support becomes difficult to operate when communication, ownership, service work, and reporting live in separate systems. A clear support operation connects those layers without hiding decisions from the team.</p><h2>Start with one operating view</h2><p>Bring supported conversations into a shared inbox and keep the customer record close. Agents should be able to understand who the customer is, what happened before, who owns the next step, and whether a service expectation is at risk.</p><h2>Turn important conversations into structured work</h2><p>Not every message needs a ticket, but issues that require investigation, coordination, or follow-up benefit from a durable record. Use priorities, owners, tasks, due dates, and SLA clocks to make the work explicit.</p><h2>Automate only what you can explain</h2><p>Deterministic rules are useful for repeatable actions such as assignment, tagging, status changes, notifications, and escalation. Keep triggers and outcomes visible so the team can review why something happened.</p><h2>Close the operating loop</h2><p>Review workload, response activity, SLA performance, customer feedback, and quality processes together. The goal is not more dashboards; it is a reliable way to identify friction and improve the workflow.</p>'],
            ['customer-accounts-and-workspaces-explained', 'Customer accounts and workspaces explained', 'Understand the difference between commercial account ownership and isolated support workspaces in a multi-tenant helpdesk.', '<p>PromptBot separates the commercial relationship from day-to-day workspace operation. That distinction helps organizations manage multiple brands or teams without mixing staff identities and operational data.</p><h2>The customer account</h2><p>A customer account owns commercial services. Eligible account members use the customer portal to manage workspaces, subscriptions, invoices, payment methods, members, account-level support, profile security, and active sessions.</p><h2>The workspace</h2><p>A workspace is a separately bounded support operation. It has its own tenant identity, domain context, staff users, channels, customers, conversations, tickets, workflows, SLAs, forms, knowledge resources, and reporting data.</p><h2>Why separate the identities?</h2><p>A billing owner does not automatically need access to every customer conversation, and a support agent does not automatically need authority over subscriptions or invoices. Separate portal, platform, and tenant guards keep those responsibilities explicit.</p><h2>When multiple workspaces help</h2><p>Multiple workspaces can support separate brands, regions, business units, or service teams. The customer account keeps ownership and billing organized while each workspace retains its operational boundary.</p>'],
            ['practical-sla-and-escalation-workflows', 'Practical SLA and escalation workflows', 'Build understandable service expectations using priorities, clocks, explicit ownership, and deterministic escalation rules.', '<p>SLAs are most useful when they guide everyday action instead of becoming a report reviewed after the fact. A practical setup connects service targets to priorities, ownership, working hours, and explicit escalation steps.</p><h2>Define targets that match the service</h2><p>Start with a small number of priority levels and document what each means. Attach response and resolution expectations that the team can realistically operate.</p><h2>Make the clock visible</h2><p>Agents and managers should see which work is approaching a target and which items have breached. Visibility supports intervention before a customer experience deteriorates.</p><h2>Use explicit escalation actions</h2><p>Escalations can notify a responsible group, change priority, assign ownership, or create a follow-up task. Keep each trigger and action understandable so operators can audit the workflow.</p><h2>Review outcomes, not only breaches</h2><p>Measure whether the targets reflect actual customer needs, whether ownership is clear, and where dependencies slow resolution. Adjust the process before simply tightening the numbers.</p>'],
        ];

        foreach ($posts as [$slug, $title, $excerpt, $content]) {
            BlogPost::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => $content,
                'status' => 'published',
                'published_at' => now(),
                'robots_index' => true,
                'seo' => ['title' => $title, 'description' => $excerpt],
            ]);
        }
    }

    private function seedNavigation(array $pages): void
    {
        $desired = ['features' => 'Features', 'pricing' => 'Pricing', 'contact' => 'Contact', 'blog' => 'Blog', 'resources' => 'Resources'];
        WebsiteNavigationItem::where('menu_group', 'header')->whereNotIn('label', array_values($desired))->delete();
        foreach ($desired as $slug => $label) {
            $isBlog = $slug === 'blog';
            WebsiteNavigationItem::updateOrCreate(['menu_group' => 'header', 'label' => $label], [
                'type' => $isBlog ? 'external' : 'internal',
                'website_page_id' => $isBlog ? null : $pages[$slug]->id,
                'url' => '/'.$slug,
                'sort_order' => array_search($slug, array_keys($desired), true) + 1,
                'is_active' => true,
                'style' => 'link',
            ]);
        }
    }

    private function seedFooter(): void
    {
        WebsiteFooterLink::where('label', 'Security')->delete();
        $groups = ['Product' => ['Features' => '/features', 'Pricing' => '/pricing', 'Customer Portal' => '/account/login', 'Help Center' => '/features'], 'Resources' => ['Resources' => '/resources', 'Blog' => '/blog', 'Contact' => '/contact'], 'Company' => ['About' => '/about'], 'Legal' => ['Privacy' => '/privacy', 'Terms' => '/terms']];
        $sortOrder = 1;
        foreach ($groups as $group => $links) {
            foreach ($links as $label => $url) {
                WebsiteFooterLink::updateOrCreate(['group' => $group, 'label' => $label], ['url' => $url, 'sort_order' => $sortOrder++]);
            }
        }
    }

    private function seedSettings(): void
    {
        $settings = ['general' => ['site_name' => 'PromptBot', 'footer_description' => 'A self-hosted omnichannel helpdesk for clear, connected customer support operations.'], 'theme' => ['primary_color' => '#064E3B', 'secondary_color' => '#475569', 'accent_color' => '#059669', 'heading_font' => 'Manrope', 'body_font' => 'Inter', 'button_radius' => '12px', 'card_radius' => '16px', 'container_width' => '1280px'], 'seo' => ['default_meta_title_format' => '{title} · {site_name}', 'default_description' => 'Bring customer conversations, tickets, tasks, SLAs, workflows, and support operations together with PromptBot.']];
        $legacyThemeDefaults = ['primary_color' => '#0F172A', 'accent_color' => '#4F46E5'];
        foreach ($settings as $group => $values) {
            foreach ($values as $key => $value) {
                $setting = WebsiteSetting::firstOrCreate(['group' => $group, 'key' => $key], ['value' => ['value' => $value]]);
                if ($group === 'theme' && isset($legacyThemeDefaults[$key]) && data_get($setting->value, 'value') === $legacyThemeDefaults[$key]) {
                    $setting->update(['value' => ['value' => $value]]);
                }
            }
        }
    }
}
