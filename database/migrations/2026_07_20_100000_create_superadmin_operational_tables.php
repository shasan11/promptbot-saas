<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createFinancialTables();
        $this->createWebsiteTables();
        $this->createCustomerTables();
        $this->createSystemTables();
    }

    public function down(): void
    {
        foreach ([
            'maintenance_windows',
            'provider_health_logs',
            'ai_model_configs',
            'integration_definitions',
            'support_messages',
            'support_tickets',
            'announcements',
            'notification_templates',
            'blog_posts',
            'legal_documents',
            'website_scripts',
            'website_redirects',
            'media',
            'website_footer_links',
            'website_navigation_items',
            'website_sections',
            'website_page_versions',
            'website_pages',
            'website_themes',
            'website_settings',
            'usage_metrics',
            'gateway_webhooks',
            'gateways',
            'exchange_rates',
            'currencies',
            'tax_rates',
            'coupons',
            'refunds',
            'invoice_items',
            'invoices',
            'payment_attempts',
            'payments',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createFinancialTables(): void
    {
        $this->create('payments', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->index();
            $table->string('provider')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
        });

        $this->create('payment_attempts', function (Blueprint $table): void {
            $table->uuid('payment_id')->nullable()->index();
            $table->string('provider')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('payload')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('attempted_at')->nullable();
        });

        $this->create('invoices', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->index();
            $table->string('number')->unique();
            $table->string('status')->default('draft')->index();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->date('issued_on')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('metadata')->nullable();
        });

        $this->create('invoice_items', function (Blueprint $table): void {
            $table->uuid('invoice_id')->index();
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('metadata')->nullable();
        });

        $this->create('refunds', function (Blueprint $table): void {
            $table->uuid('payment_id')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending')->index();
            $table->text('reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
        });

        $this->create('coupons', function (Blueprint $table): void {
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type')->default('percent');
            $table->decimal('value', 12, 2)->default(0);
            $table->integer('max_redemptions')->nullable();
            $table->integer('redemptions')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
        });

        $this->create('tax_rates', function (Blueprint $table): void {
            $table->string('name');
            $table->string('country')->nullable()->index();
            $table->decimal('rate', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
        });

        $this->create('currencies', function (Blueprint $table): void {
            $table->string('code', 3)->unique();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->boolean('is_active')->default(true);
        });

        $this->create('exchange_rates', function (Blueprint $table): void {
            $table->string('base_currency', 3)->index();
            $table->string('target_currency', 3)->index();
            $table->decimal('rate', 18, 8);
            $table->date('effective_on')->index();
        });

        $this->create('gateways', function (Blueprint $table): void {
            $table->string('name');
            $table->string('provider')->index();
            $table->string('mode')->default('test');
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
            $table->json('encrypted_secrets')->nullable();
        });

        $this->create('gateway_webhooks', function (Blueprint $table): void {
            $table->uuid('gateway_id')->nullable()->index();
            $table->string('event_id')->nullable()->unique();
            $table->string('event_type')->nullable()->index();
            $table->string('status')->default('received')->index();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
        });

        $this->create('usage_metrics', function (Blueprint $table): void {
            $table->string('tenant_id')->index();
            $table->string('metric')->index();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->date('period_start')->index();
            $table->date('period_end')->index();
            $table->json('metadata')->nullable();
        });
    }

    private function createWebsiteTables(): void
    {
        $this->create('website_settings', fn (Blueprint $table) => $this->settingsColumns($table));
        $this->create('website_themes', function (Blueprint $table): void {
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->json('tokens')->nullable();
        });
        $this->create('website_pages', function (Blueprint $table): void {
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('status')->default('draft')->index();
            $table->json('seo')->nullable();
            $table->timestamp('published_at')->nullable();
        });
        $this->create('website_page_versions', function (Blueprint $table): void {
            $table->uuid('website_page_id')->index();
            $table->integer('version')->default(1);
            $table->json('content')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });
        $this->create('website_sections', function (Blueprint $table): void {
            $table->uuid('website_page_id')->nullable()->index();
            $table->string('type')->index();
            $table->integer('sort_order')->default(0);
            $table->json('content')->nullable();
        });
        $this->create('website_navigation_items', function (Blueprint $table): void {
            $table->string('label');
            $table->string('url');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        });
        $this->create('website_footer_links', function (Blueprint $table): void {
            $table->string('label');
            $table->string('url');
            $table->string('group')->nullable()->index();
            $table->integer('sort_order')->default(0);
        });
        $this->create('media', function (Blueprint $table): void {
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->json('metadata')->nullable();
        });
        $this->create('website_redirects', function (Blueprint $table): void {
            $table->string('from_path')->unique();
            $table->string('to_url');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
        });
        $this->create('website_scripts', function (Blueprint $table): void {
            $table->string('name');
            $table->string('placement')->default('footer');
            $table->text('script');
            $table->boolean('is_active')->default(false);
        });
        $this->create('legal_documents', function (Blueprint $table): void {
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->timestamp('published_at')->nullable();
        });
        $this->create('blog_posts', function (Blueprint $table): void {
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('seo')->nullable();
            $table->timestamp('published_at')->nullable();
        });
    }

    private function createCustomerTables(): void
    {
        $this->create('notification_templates', function (Blueprint $table): void {
            $table->string('key')->unique();
            $table->string('channel')->default('email');
            $table->string('language', 10)->default('en');
            $table->string('status')->default('draft')->index();
            $table->string('subject')->nullable();
            $table->longText('body')->nullable();
            $table->json('variables')->nullable();
        });
        $this->create('announcements', function (Blueprint $table): void {
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('targeting')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
        $this->create('support_tickets', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->index();
            $table->string('subject');
            $table->string('status')->default('open')->index();
            $table->string('priority')->default('normal')->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->timestamp('sla_due_at')->nullable();
        });
        $this->create('support_messages', function (Blueprint $table): void {
            $table->uuid('support_ticket_id')->index();
            $table->unsignedBigInteger('author_id')->nullable()->index();
            $table->boolean('internal')->default(false);
            $table->longText('body')->nullable();
            $table->json('attachments')->nullable();
        });
        $this->create('integration_definitions', function (Blueprint $table): void {
            $table->string('name');
            $table->string('provider')->index();
            $table->string('status')->default('inactive')->index();
            $table->json('settings')->nullable();
            $table->json('encrypted_secrets')->nullable();
        });
        $this->create('ai_model_configs', function (Blueprint $table): void {
            $table->string('provider')->index();
            $table->string('model');
            $table->string('purpose')->default('default')->index();
            $table->boolean('is_active')->default(false);
            $table->json('settings')->nullable();
        });
        $this->create('provider_health_logs', function (Blueprint $table): void {
            $table->string('provider')->index();
            $table->string('status')->index();
            $table->integer('latency_ms')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('checked_at')->nullable();
        });
    }

    private function createSystemTables(): void
    {
        $this->create('maintenance_windows', function (Blueprint $table): void {
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('status')->default('scheduled')->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
        });
    }

    private function create(string $name, callable $callback): void
    {
        if (Schema::hasTable($name)) {
            return;
        }

        Schema::create($name, function (Blueprint $table) use ($callback): void {
            $table->uuid('id')->primary();
            $callback($table);
            $table->timestamps();
        });
    }

    private function settingsColumns(Blueprint $table): void
    {
        $table->string('group')->index();
        $table->string('key')->index();
        $table->json('value')->nullable();
        $table->boolean('encrypted')->default(false);
        $table->boolean('is_sensitive')->default(false);
    }
};
