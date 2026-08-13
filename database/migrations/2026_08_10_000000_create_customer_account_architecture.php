<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('status')->default('invited')->index();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->boolean('two_factor_enabled')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('timezone')->nullable();
            $table->string('locale', 10)->nullable();
            $table->timestamps();
        });

        Schema::create('portal_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::table('central_users', function (Blueprint $table): void {
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_required');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
        });

        Schema::create('portal_login_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('portal_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable()->index();
            $table->string('event')->index();
            $table->boolean('successful')->default(false)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('portal_user_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->string('session_hash', 64)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_activity_at')->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('portal_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            // The customer_accounts table is created later in this additive migration.
            // Defer this FK so MySQL does not reject a reference to a table that does not yet exist.
            $table->unsignedBigInteger('customer_account_id');
            $table->boolean('billing_email')->default(true);
            $table->boolean('workspace_email')->default(true);
            $table->boolean('support_email')->default(true);
            $table->boolean('security_email')->default(true);
            $table->boolean('product_email')->default(false);
            $table->timestamps();
            $table->unique(['portal_user_id', 'customer_account_id'], 'portal_notification_preference_unique');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->unsignedInteger('per_account_limit')->nullable()->after('max_redemptions');
            $table->string('billing_interval')->nullable()->after('per_account_limit')->index();
            $table->decimal('minimum_amount', 12, 2)->nullable()->after('billing_interval');
        });

        Schema::create('coupon_plans', function (Blueprint $table): void {
            $table->uuid('coupon_id');
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'plan_id']);
            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
        });
        Schema::create('coupon_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('coupon_id');
            // Defer the customer account FK until customer_accounts exists (required by MySQL/InnoDB).
            $table->unsignedBigInteger('customer_account_id');
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('invoice_id')->nullable();
            $table->string('code_snapshot');
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('applied')->index();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
            $table->foreign('coupon_id')->references('id')->on('coupons')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->index(['coupon_id', 'subscription_id']);
        });

        Schema::create('customer_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_uuid')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('account_number')->unique();
            $table->string('status')->default('active')->index();
            $table->string('type')->default('business')->index();
            $table->foreignId('primary_owner_user_id')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->string('billing_email')->nullable()->index();
            $table->string('billing_phone')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('default_currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->string('billing_mode')->default('per_service')->index();
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('portal_notification_preferences', function (Blueprint $table): void {
            $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->cascadeOnDelete();
        });
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->cascadeOnDelete();
        });

        Schema::create('customer_account_limits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key')->index();
            $table->string('scope')->default('account')->index();
            $table->decimal('limit_value', 16, 2);
            $table->string('unit')->default('count');
            $table->string('period')->nullable();
            $table->boolean('is_enforced')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['customer_account_id', 'feature_key', 'period'], 'customer_account_limit_unique');
        });

        Schema::create('customer_account_users', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member')->index();
            $table->boolean('can_manage_services')->default(false);
            $table->boolean('can_manage_billing')->default(false);
            $table->boolean('can_manage_members')->default(false);
            $table->boolean('can_manage_support')->default(true);
            $table->string('service_access')->default('all')->index();
            $table->foreignId('invited_by')->nullable()->constrained('portal_users')->nullOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->primary(['customer_account_id', 'portal_user_id']);
        });

        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_account_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('billing_name')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('vat_number')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('portal_payment_methods', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('provider_reference');
            $table->string('type')->default('card');
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->unsignedTinyInteger('expires_month')->nullable();
            $table->unsignedSmallInteger('expires_year')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'provider_reference']);
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->uuid('invoice_id')->nullable()->after('customer_account_id')->index();
            $table->string('provider_reference')->nullable()->after('idempotency_key');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('metadata')->nullable();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
        });

        Schema::create('subscription_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('type')->index();
            $table->foreignId('old_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->foreignId('new_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('old_billing_interval')->nullable();
            $table->string('new_billing_interval')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('effective_at')->index();
            $table->timestamps();
        });

        Schema::create('customer_account_notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('central_user_id')->nullable()->constrained('central_users')->nullOnDelete();
            $table->string('type')->default('internal')->index();
            $table->text('body');
            $table->boolean('is_customer_visible')->default(false);
            $table->timestamps();
        });

        Schema::create('customer_account_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('actor_type')->nullable();
            $table->string('actor_id')->nullable();
            $table->string('event')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_customer_visible')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('workspace_purchase_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('tenant_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->json('request_snapshot');
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_account_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('role')->default('member');
            $table->boolean('can_manage_services')->default(false);
            $table->boolean('can_manage_billing')->default(false);
            $table->boolean('can_manage_members')->default(false);
            $table->boolean('can_manage_support')->default(true);
            $table->string('service_access')->default('all');
            $table->json('tenant_ids')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->constrained('portal_users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_account_id', 'email']);
        });

        Schema::create('portal_notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('public_uuid')->index();
        });
        Schema::create('customer_account_user_tenants', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portal_user_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id');
            $table->timestamps();
            $table->primary(['customer_account_id', 'portal_user_id', 'tenant_id'], 'account_user_tenant_primary');
            $table->foreign(['customer_account_id', 'portal_user_id'], 'account_user_membership_fk')
                ->references(['customer_account_id', 'portal_user_id'])->on('customer_account_users')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
        Schema::table('plans', function (Blueprint $table): void {
            $table->boolean('is_public')->default(true)->after('is_active')->index();
        });
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('public_uuid')->index();
            $table->foreignId('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->uuid('coupon_id')->nullable()->after('pending_plan_id');
            $table->string('pending_billing_interval')->nullable()->after('billing_interval');
            $table->timestamp('pending_change_effective_at')->nullable();
            $table->timestamp('cancel_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('cancellation_feedback')->nullable();
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('id')->index();
            $table->json('billing_snapshot')->nullable();
            $table->decimal('discount_total', 12, 2)->default(0);
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->uuid('coupon_redemption_id')->nullable();
            $table->decimal('tax_total', 12, 2)->default(0);
        });

        Schema::table('subscriptions', fn (Blueprint $table) => $table->foreign('coupon_id')->references('id')->on('coupons')->nullOnDelete());
        Schema::table('invoice_items', fn (Blueprint $table) => $table->foreign('coupon_redemption_id')->references('id')->on('coupon_redemptions')->nullOnDelete());
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('id')->index();
            $table->string('tenant_id')->nullable()->change();
        });
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->foreignId('customer_account_id')->nullable()->after('id')->index();
            $table->foreignId('portal_user_id')->nullable()->after('tenant_id')->constrained('portal_users')->nullOnDelete();
            $table->string('tenant_id')->nullable()->change();
        });
        Schema::table('support_ticket_messages', function (Blueprint $table): void {
            $table->foreignId('portal_user_id')->nullable()->after('central_user_id')->constrained('portal_users')->nullOnDelete();
        });

        Schema::table('website_pages', function (Blueprint $table): void {
            $table->string('page_type')->default('standard')->after('slug')->index();
            $table->string('template')->default('default');
            $table->string('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->json('open_graph')->nullable();
            $table->json('twitter')->nullable();
            $table->json('schema_json')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('central_users')->nullOnDelete();
            $table->softDeletes();
        });
        Schema::table('website_sections', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false)->index();
        });
        Schema::table('website_navigation_items', function (Blueprint $table): void {
            $table->string('menu_group')->default('header')->index();
            $table->string('type')->default('external');
            $table->uuid('website_page_id')->nullable()->index();
            $table->uuid('parent_id')->nullable()->index();
            $table->boolean('open_new_tab')->default(false);
            $table->string('style')->default('link');
        });
        Schema::table('media', function (Blueprint $table): void {
            $table->string('filename')->nullable()->index();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('central_users')->nullOnDelete();
        });
        Schema::table('website_redirects', function (Blueprint $table): void {
            $table->unsignedBigInteger('hit_count')->default(0);
        });
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->text('excerpt')->nullable();
            $table->string('featured_image')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('central_users')->nullOnDelete();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->string('canonical_url')->nullable();
            $table->boolean('robots_index')->default(true);
            $table->softDeletes();
        });

        Schema::create('website_forms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('fields');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('website_form_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('website_form_id')->index();
            $table->string('name')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('company')->nullable();
            $table->string('phone')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->json('utm')->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->string('status')->default('new')->index();
            $table->string('ip_hash', 64)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('website_form_id')->references('id')->on('website_forms')->cascadeOnDelete();
        });
        Schema::create('website_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('website_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('website_post_categories', function (Blueprint $table): void {
            $table->uuid('blog_post_id');
            $table->uuid('website_category_id');
            $table->primary(['blog_post_id', 'website_category_id']);
        });
        Schema::create('website_post_tags', function (Blueprint $table): void {
            $table->uuid('blog_post_id');
            $table->uuid('website_tag_id');
            $table->primary(['blog_post_id', 'website_tag_id']);
        });

        $this->backfillLegacyTenants();

        Schema::table('tenants', fn (Blueprint $table) => $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->nullOnDelete());
        Schema::table('subscriptions', fn (Blueprint $table) => $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->nullOnDelete());
        Schema::table('invoices', fn (Blueprint $table) => $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->nullOnDelete());
        Schema::table('payments', fn (Blueprint $table) => $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->nullOnDelete());
        Schema::table('support_tickets', fn (Blueprint $table) => $table->foreign('customer_account_id')->references('id')->on('customer_accounts')->nullOnDelete());
    }

    private function backfillLegacyTenants(): void
    {
        DB::table('tenants')->whereNull('customer_account_id')->orderBy('id')->lazy()->each(function (object $tenant): void {
            $accountId = DB::table('customer_accounts')->insertGetId([
                'public_uuid' => (string) Str::uuid(),
                'name' => $tenant->company_name,
                'account_number' => 'ACC-LEGACY-'.strtoupper(substr(hash('sha256', (string) $tenant->id), 0, 10)),
                'status' => in_array($tenant->status, ['active', 'trial', 'suspended'], true) ? $tenant->status : 'active',
                'type' => 'business',
                'default_currency' => 'USD',
                'timezone' => 'UTC',
                'locale' => 'en',
                'billing_mode' => 'per_service',
                'metadata' => json_encode(['legacy_tenant_id' => $tenant->id]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('tenants')->where('id', $tenant->id)->update(['customer_account_id' => $accountId]);
            DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['customer_account_id' => $accountId]);
            DB::table('invoices')->where('tenant_id', $tenant->id)->update(['customer_account_id' => $accountId]);
            DB::table('payments')->where('tenant_id', $tenant->id)->update(['customer_account_id' => $accountId]);
            DB::table('support_tickets')->where('tenant_id', $tenant->id)->update(['customer_account_id' => $accountId]);

            DB::table('billing_profiles')->insert([
                'customer_account_id' => $accountId,
                'company_name' => $tenant->company_name,
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Customer account architecture is intentionally irreversible because it backfills production ownership data.');
    }
};
