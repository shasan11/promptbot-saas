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

        // In-memory only — forgetCachedPermissions() would hit the configured
        // cache store's backing table, which doesn't exist yet on a tenant
        // database that's mid-migration (e.g. during initial provisioning).
        // Each tenant has its own physically isolated cache table, so simply
        // not touching the persisted cache here is safe: it either doesn't
        // exist yet (fresh tenant) or already holds this same tenant's own
        // valid data (existing tenant), never another tenant's.
        $registrar->clearPermissionsCollection();
    }

    private function useCentralModels(): void
    {
        config(['permission.table_names' => self::CENTRAL_TABLE_NAMES]);

        $registrar = $this->app->make(PermissionRegistrar::class);
        $registrar->setRoleClass(PlatformRole::class);
        $registrar->setPermissionClass(PlatformPermission::class);

        // The cache store may be database-backed and the tenant connection is
        // already being torn down by the time TenancyEnded fires, so clearing
        // the in-memory cache is enough here — the next central request will
        // rebuild the Spatie cache against the central connection naturally.
        $registrar->clearPermissionsCollection();
    }
}
