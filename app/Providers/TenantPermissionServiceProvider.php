<?php

namespace App\Providers;

use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\TenantPermission;
use App\Models\TenantRole;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Spatie's PermissionRegistrar is a singleton bound to a single Role/Permission
 * model pair, and its HasRoles/HasPermissions traits read pivot table names
 * live from config('permission.table_names') on every relation call. Central
 * users are authorized against PlatformRole/PlatformPermission (guard
 * "central", tables prefixed platform_*); tenant users are authorized against
 * TenantRole/TenantPermission (guard "tenant", tables unprefixed — each
 * tenant has its own physical database). Swapping both the registrar's model
 * classes and the table_names config on tenancy init/end lets both guards
 * share the one Spatie package install without either leaking into the
 * other's queries.
 */
class TenantPermissionServiceProvider extends ServiceProvider
{
    private const CENTRAL_TABLE_NAMES = [
        'roles' => 'platform_roles',
        'permissions' => 'platform_permissions',
        'model_has_permissions' => 'platform_model_has_permissions',
        'model_has_roles' => 'platform_model_has_roles',
        'role_has_permissions' => 'platform_role_has_permissions',
    ];

    private const TENANT_TABLE_NAMES = [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ];

    public function boot(): void
    {
        $this->app['events']->listen(TenancyInitialized::class, function (): void {
            $this->useTenantModels();
        });

        $this->app['events']->listen(TenancyEnded::class, function (): void {
            $this->useCentralModels();
        });
    }

    private function useTenantModels(): void
    {
        config(['permission.table_names' => self::TENANT_TABLE_NAMES]);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->setRoleClass(TenantRole::class);
        $registrar->setPermissionClass(TenantPermission::class);

        // Permission caching uses the array store. Flush both the registrar
        // collection and its process-local cache so central permissions can
        // never survive into a tenant authorization check.
        $registrar->forgetCachedPermissions();
    }

    private function useCentralModels(): void
    {
        config(['permission.table_names' => self::CENTRAL_TABLE_NAMES]);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->setRoleClass(PlatformRole::class);
        $registrar->setPermissionClass(PlatformPermission::class);

        // Remove the tenant catalog before the next central request rebuilds
        // permissions using the central models and tables above.
        $registrar->forgetCachedPermissions();
    }
}
