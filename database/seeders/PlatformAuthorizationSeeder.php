<?php

namespace Database\Seeders;

use App\Models\CentralUser;
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
            ],
            'ai_rag' => [
                'ai_provider' => env('AI_PROVIDER', 'openai'),
                'ai_model' => env('AI_MODEL', 'gpt-4.1-mini'),
                'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
                'rag_vector_store' => env('RAG_VECTOR_STORE', 'pgvector'),
                'rag_top_k' => 5,
                'rag_chunk_size' => 1000,
                'rag_chunk_overlap' => 150,
            ],
            'branding' => [
                'company_name' => 'PromptBot',
                'primary_color' => '#0F172A',
                'secondary_color' => '#4F46E5',
                'accent_color' => '#22C55E',
                'copyright_text' => '© '.date('Y').' PromptBot. All rights reserved.',
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
    }
}
