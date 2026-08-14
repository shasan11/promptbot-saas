<?php

namespace Database\Seeders;

use App\Models\CentralUser;
use App\Models\NotificationTemplate;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class PlatformAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'dashboard.view',
            'customers.view',
            'customers.manage',
            'revenue.view',
            'tenants.view',
            'tenants.create',
            'tenants.update',
            'tenants.suspend',
            'tenants.delete',
            'tenants.impersonate',
            'plans.view',
            'plans.create',
            'plans.update',
            'plans.archive',
            'subscriptions.view',
            'subscriptions.update',
            'subscriptions.cancel',
            'features.view',
            'features.manage',
            'administrators.view',
            'administrators.manage',
            'roles.manage',
            'permissions.manage',
            'settings.view',
            'settings.update',
            'audit_logs.view',
            'login_attempts.view',
            'payments.view',
            'payments.manage',
            'invoices.view',
            'invoices.manage',
            'coupons.view',
            'coupons.manage',
            'gateways.manage',
            'usage.view',
            'website.view',
            'website.manage',
            'website.custom_html',
            'communications.view',
            'communications.manage',
            'support.view',
            'support.manage',
            'integrations.view',
            'integrations.manage',
            'operations.view',
            'backups.manage',
            'security.manage',
            'maintenance.manage',
        ];

        $permissionModels = collect($permissions)->mapWithKeys(fn (string $permission) => [
            $permission => PlatformPermission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'central'],
                ['id' => (string) Str::uuid()]
            ),
        ]);

        $owner = PlatformRole::firstOrCreate(
            ['name' => 'Platform Owner', 'guard_name' => 'central'],
            ['id' => (string) Str::uuid()]
        );
        $owner->syncPermissions($permissionModels->values());

        $auditor = PlatformRole::firstOrCreate(
            ['name' => 'Read-Only Auditor', 'guard_name' => 'central'],
            ['id' => (string) Str::uuid()]
        );
        $auditor->syncPermissions($permissionModels->filter(fn ($permission, string $name) => str_ends_with($name, '.view'))->values());

        $rolePermissions = [
            'Platform Administrator' => $permissions,
            'Billing Manager' => [
                'dashboard.view', 'customers.view', 'revenue.view', 'plans.view', 'plans.update',
                'subscriptions.view', 'subscriptions.update', 'subscriptions.cancel', 'payments.view',
                'payments.manage', 'invoices.view', 'invoices.manage', 'coupons.view', 'coupons.manage',
                'gateways.manage', 'usage.view', 'audit_logs.view',
            ],
            'Support Manager' => [
                'dashboard.view', 'customers.view', 'tenants.view', 'support.view', 'support.manage',
                'communications.view', 'communications.manage', 'audit_logs.view',
            ],
            'Content Manager' => [
                'dashboard.view', 'website.view', 'website.manage', 'communications.view',
                'communications.manage',
            ],
            'Operations Manager' => [
                'dashboard.view', 'customers.view', 'customers.manage', 'tenants.view', 'tenants.create',
                'tenants.update', 'tenants.suspend', 'usage.view', 'operations.view', 'integrations.view',
                'integrations.manage', 'maintenance.manage', 'audit_logs.view', 'login_attempts.view',
            ],
            'Auditor' => array_values(array_filter($permissions, fn (string $permission) => str_ends_with($permission, '.view'))),
        ];

        foreach ($rolePermissions as $roleName => $grants) {
            $role = PlatformRole::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'central'],
                ['id' => (string) Str::uuid()]
            );
            $role->syncPermissions($permissionModels->only($grants)->values());
        }

        CentralUser::query()
            ->where('role', 'super_admin')
            ->orWhere('email', env('CENTRAL_ADMIN_EMAIL', 'admin@example.com'))
            ->get()
            ->each(function (CentralUser $user) use ($owner): void {
                $user->forceFill([
                    'role' => 'super_admin',
                    'is_active' => true,
                ])->save();

                $user->assignRole($owner);
            });

        $defaults = [
            'general' => [
                'platform_name' => 'PromptBot',
                'platform_url' => config('app.url'),
                'support_email' => env('CENTRAL_ADMIN_EMAIL', 'admin@example.com'),
                'timezone' => config('app.timezone', 'UTC'),
                'default_locale' => config('app.locale', 'en'),
                'default_currency' => 'USD',
                'tenant_base_domain' => config('saas.tenant_base_domain', 'localhost'),
            ],
            'email' => [
                'from_name' => config('mail.from.name', 'PromptBot'),
                'from_address' => config('mail.from.address', 'hello@example.com'),
            ],
            'mail' => [
                'mailer' => config('mail.default', 'log'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.scheme') ?? config('mail.mailers.smtp.encryption'),
            ],
            'payment' => [
                'default_gateway' => 'manual',
                'invoice_prefix' => 'INV',
                'tax_rate' => 0,
                'billing_mode' => 'per_service',
                'trial_days' => 14,
                'grace_period_days' => 7,
                'allow_plan_changes' => true,
                'allow_self_service_cancellation' => true,
                'prorate_plan_changes' => true,
            ],
            'registration' => [
                'enabled' => true,
                'mode' => 'enabled',
                'require_email_verification' => true,
                'email_verification_required' => true,
                'default_plan' => 'starter',
                'require_terms_acceptance' => true,
                'require_payment_before_provisioning' => false,
                'maximum_workspaces_per_account' => 0,
            ],
            'billing' => [
                'invoice_number_start' => 1,
                'payment_terms_days' => 0,
                'grace_period_days' => 7,
                'billing_mode_support' => 'both',
                'proration_policy' => 'next_invoice',
                'plan_change_policy' => 'customer_choice',
                'allow_immediate_cancellation' => false,
            ],
            'tax' => [
                'enabled' => false,
                'default_rate' => 0,
                'prices_include_tax' => false,
                'tax_label' => 'Tax',
            ],
            'trials' => [
                'default_trial_days' => 14,
                'trial_ending_notice_days' => 7,
                'allow_trial_without_payment_method' => true,
            ],
            'tenant_provisioning' => [
                'mode' => config('saas.db_provisioning_mode', 'manual'),
                'default_region' => env('TENANT_DEFAULT_REGION'),
                'automatic_retry_count' => 1,
                'customer_can_retry' => true,
            ],
            'notifications' => [
                'billing_events_enabled' => true,
                'workspace_events_enabled' => true,
                'support_events_enabled' => true,
                'renewal_notice_days' => 7,
            ],
            'customer_portal' => [
                'enabled' => true,
                'google_login_enabled' => false,
                'allow_workspace_creation' => true,
                'allow_plan_changes' => true,
                'allow_cancellations' => true,
                'allow_member_invitations' => true,
                'allow_billing_profile_updates' => true,
                'allow_support_tickets' => true,
                'support_tickets_enabled' => true,
            ],
            'maintenance' => [
                'banner_enabled' => false,
                'block_new_provisioning' => false,
            ],
            'branding' => [
                'company_name' => 'PromptBot',
                'primary_color' => '#0F172A',
                'secondary_color' => '#4F46E5',
                'accent_color' => '#22C55E',
                'copyright_text' => "\u{00A9} ".date('Y').' PromptBot. All rights reserved.',
            ],
            'security' => [
                'login_attempt_limit' => 5,
                'lockout_duration_minutes' => 15,
                'password_expiry_days' => 90,
            ],
        ];

        foreach ($defaults as $group => $settings) {
            foreach ($settings as $key => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                PlatformSetting::firstOrCreate(
                    ['group' => $group, 'key' => $key],
                    [
                        'id' => (string) Str::uuid(),
                        'value' => ['value' => $value],
                        'encrypted' => false,
                        'is_sensitive' => false,
                    ]
                );
            }
        }

        $templates = [
            'welcome' => ['Welcome to {{platform_name}}', '<h1>Welcome, {{customer_name}}</h1><p>Your customer account for {{account_name}} is ready.</p><p><a href="{{action_url}}">Open customer portal</a></p>', ['platform_name', 'customer_name', 'account_name', 'action_url']],
            'account_invitation' => ['You are invited to {{account_name}}', '<h1>Join {{account_name}}</h1><p>Hello {{customer_name}}, you have been invited to manage services in {{platform_name}}.</p><p><a href="{{action_url}}">Accept invitation</a></p>', ['platform_name', 'customer_name', 'account_name', 'action_url']],
            'email_verification' => ['Verify your {{platform_name}} email', '<h1>Verify your email</h1><p>Hello {{customer_name}}, confirm your email to finish setting up your account.</p><p><a href="{{action_url}}">Verify email</a></p>', ['platform_name', 'customer_name', 'action_url']],
            'password_reset' => ['Reset your {{platform_name}} password', '<h1>Password reset</h1><p>Hello {{customer_name}}, use the secure link below to choose a new password.</p><p><a href="{{action_url}}">Reset password</a></p>', ['platform_name', 'customer_name', 'action_url']],
            'workspace_provisioned' => ['{{workspace_name}} is ready', '<h1>Your service is ready</h1><p>Hello {{customer_name}}, {{workspace_name}} has been provisioned for {{account_name}}.</p><p><a href="{{action_url}}">Open workspace</a></p>', ['customer_name', 'account_name', 'workspace_name', 'action_url']],
            'invoice_issued' => ['Invoice {{invoice_number}} from {{platform_name}}', '<h1>New invoice</h1><p>Invoice {{invoice_number}} for {{account_name}} totals {{invoice_total}}.</p><p><a href="{{action_url}}">View invoice</a></p>', ['platform_name', 'account_name', 'invoice_number', 'invoice_total', 'action_url']],
            'payment_received' => ['Payment received by {{platform_name}}', '<h1>Thank you</h1><p>We received {{payment_amount}} for {{account_name}}.</p><p><a href="{{action_url}}">View billing</a></p>', ['platform_name', 'account_name', 'payment_amount', 'action_url']],
            'payment_failed' => ['Payment failed for {{account_name}}', '<h1>Payment failed</h1><p>We could not process {{payment_amount}} for {{account_name}}.</p><p><a href="{{action_url}}">Update payment method or retry</a></p>', ['account_name', 'payment_amount', 'action_url']],
            'trial_ending' => ['Your {{platform_name}} trial is ending', '<h1>Trial ending soon</h1><p>The trial for {{workspace_name}} is ending soon.</p><p><a href="{{action_url}}">Review subscription</a></p>', ['platform_name', 'workspace_name', 'action_url']],
            'subscription_cancelled' => ['Subscription cancelled for {{workspace_name}}', '<h1>Cancellation confirmed</h1><p>The subscription for {{workspace_name}} has been cancelled.</p><p><a href="{{action_url}}">View subscription</a></p>', ['workspace_name', 'action_url']],
            'subscription_renewal' => ['Subscription renewal for {{workspace_name}}', '<h1>Subscription renewal</h1><p>The subscription for {{workspace_name}} is approaching its renewal date.</p><p><a href="{{action_url}}">Review subscription</a></p>', ['workspace_name', 'action_url']],
            'workspace_suspended' => ['Action required for {{workspace_name}}', '<h1>Workspace suspended</h1><p>{{workspace_name}} has been suspended. Open the customer portal for details and support.</p><p><a href="{{action_url}}">View workspace</a></p>', ['workspace_name', 'action_url']],
            'support_update' => ['Update on ticket {{ticket_number}}', '<h1>Support ticket updated</h1><p>Hello {{customer_name}}, there is a new update on {{ticket_number}}.</p><p><a href="{{action_url}}">View ticket</a></p>', ['customer_name', 'ticket_number', 'action_url']],
            'plan_changed' => ['Your plan for {{workspace_name}} changed', '<h1>Plan updated</h1><p>The subscription plan for {{workspace_name}} has been updated.</p><p><a href="{{action_url}}">Review subscription</a></p>', ['workspace_name', 'action_url']],
            'invoice_overdue' => ['Invoice {{invoice_number}} is overdue', '<h1>Invoice overdue</h1><p>Invoice {{invoice_number}} for {{account_name}} requires attention.</p><p><a href="{{action_url}}">Review invoice</a></p>', ['invoice_number', 'account_name', 'action_url']],
            'workspace_provisioning_failed' => ['We need your attention for {{workspace_name}}', '<h1>Workspace setup needs attention</h1><p>We could not complete setup for {{workspace_name}}. Review the service or contact support.</p><p><a href="{{action_url}}">Review workspace</a></p>', ['workspace_name', 'action_url']],
            'member_removed' => ['Access changed for {{account_name}}', '<h1>Account access changed</h1><p>Your membership in {{account_name}} has been removed.</p>', ['account_name']],
        ];

        foreach ($templates as $key => [$subject, $body, $variables]) {
            NotificationTemplate::firstOrCreate(
                ['key' => $key],
                ['channel' => 'email', 'language' => 'en', 'status' => 'active', 'subject' => $subject, 'body' => $body, 'variables' => $variables]
            );
        }
    }
}
