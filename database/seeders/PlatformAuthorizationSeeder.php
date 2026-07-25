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
            'tenants.activate',
            'tenants.archive',
            'tenants.delete',
            'tenants.impersonate',
            'tenants.migrate',
            'tenants.seed',
            'tenants.backup',
            'tenants.restore',
            'plans.view',
            'plans.create',
            'plans.update',
            'plans.archive',
            'plans.version',
            'subscriptions.view',
            'subscriptions.create',
            'subscriptions.update',
            'subscriptions.change_plan',
            'subscriptions.pause',
            'subscriptions.resume',
            'subscriptions.cancel',
            'features.view',
            'features.manage',
            'feature_flags.manage',
            'administrators.view',
            'administrators.invite',
            'administrators.update',
            'administrators.suspend',
            'administrators.delete',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
            'settings.view',
            'settings.update',
            'audit_logs.view',
            'login_attempts.view',
            'payments.view',
            'payments.retry',
            'payments.record_manual',
            'payments.refund',
            'payments.reconcile',
            'invoices.view',
            'invoices.create',
            'invoices.finalize',
            'invoices.send',
            'invoices.void',
            'invoices.credit',
            'coupons.view',
            'coupons.manage',
            'taxes.manage',
            'currencies.manage',
            'gateways.manage',
            'usage.view',
            'usage.adjust',
            'usage.reset',
            'usage.export',
            'website.view',
            'website.edit',
            'website.publish',
            'website.scripts.manage',
            'website.media.manage',
            'communications.view',
            'communications.manage',
            'announcements.manage',
            'support.view',
            'support.manage',
            'integrations.view',
            'integrations.manage',
            'providers.manage',
            'ai_models.manage',
            'operations.view',
            'operations.jobs.manage',
            'operations.backups.manage',
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
            ->where('role', 'platform_owner')
            ->get()
            ->each(function (CentralUser $user) use ($owner): void {
                $user->forceFill([
                    'role' => 'platform_owner',
                    'is_active' => true,
                    'two_factor_required' => true,
                ])->save();

                $user->assignRole($owner);
            });

        PlatformSetting::firstOrCreate(
            ['group' => 'general', 'key' => 'platform_name'],
            ['id' => (string) Str::uuid(), 'value' => ['value' => 'PromptBot'], 'encrypted' => false, 'is_sensitive' => false]
        );

        PlatformSetting::firstOrCreate(
            ['group' => 'security', 'key' => 'login_attempt_limit'],
            ['id' => (string) Str::uuid(), 'value' => ['value' => 5], 'encrypted' => false, 'is_sensitive' => false]
        );
    }
}
