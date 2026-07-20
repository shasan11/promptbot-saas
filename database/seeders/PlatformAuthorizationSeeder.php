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
