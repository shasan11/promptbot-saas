<?php

namespace Tests\Concerns;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions a real, physically isolated tenant MySQL database for feature
 * tests that need to exercise the `tenant` guard — there is no lighter-weight
 * (e.g. sqlite) tenant connection configured in this app, so this mirrors
 * production as closely as a test can. Databases created here are dropped
 * in tearDown() by tests that opt in via $this->tenantsToCleanUp.
 */
trait InteractsWithTenancy
{
    /** @var array<int, string> */
    protected array $tenantsToCleanUp = [];

    protected function createTenantWithDomain(?string $slug = null): array
    {
        $slug ??= 'test-'.Str::random(8);
        $database = 'tenant_'.str_replace('-', '_', $slug);

        DB::connection('tenant_template')->statement("CREATE DATABASE IF NOT EXISTS `{$database}`");

        $tenant = Tenant::create([
            'id' => $slug,
            'company_name' => 'Test Co '.$slug,
            'slug' => $slug,
            'status' => TenantStatus::Active,
            'tenancy_db_connection' => 'tenant_template',
            'tenancy_db_name' => $database,
            'tenancy_db_host' => config('database.connections.tenant_template.host'),
            'tenancy_db_port' => config('database.connections.tenant_template.port'),
            'tenancy_db_username' => config('database.connections.tenant_template.username'),
            'tenancy_db_password' => config('database.connections.tenant_template.password'),
        ]);

        $domain = "{$slug}.test-tenant.localhost";
        $tenant->domains()->create(['domain' => $domain, 'type' => 'subdomain', 'is_primary' => true, 'verification_status' => 'verified', 'verified_at' => now()]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
        Artisan::call('tenants:seed', ['--tenants' => [$tenant->id], '--class' => 'Database\\Seeders\\TenantDatabaseSeeder', '--force' => true]);

        $this->tenantsToCleanUp[] = $slug;

        return [$tenant, $domain];
    }

    /**
     * Runs tenancy()->initialize()/end() exactly once even when creating
     * several users, since Stancl's connection-swap combined with the
     * sqlite :memory: central test connection doesn't tolerate being
     * cycled repeatedly within a single test process.
     */
    protected function createTenantUsers(Tenant $tenant, array $specs): array
    {
        tenancy()->initialize($tenant);

        try {
            return array_map(function (array $spec) {
                $attributes = $spec['attributes'] ?? [];
                $roleName = array_key_exists('role', $spec) ? $spec['role'] : 'Tenant Administrator';
                $user = User::factory()->create(array_merge(['status' => 'active', 'email_verified_at' => now()], $attributes));

                if ($roleName) {
                    $role = TenantRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'tenant'], ['label' => $roleName]);
                    $user->assignRole($role);
                }

                return $user;
            }, $specs);
        } finally {
            tenancy()->end();
        }
    }

    protected function createTenantUser(Tenant $tenant, array $attributes = [], ?string $roleName = 'Tenant Administrator'): User
    {
        return $this->createTenantUsers($tenant, [['attributes' => $attributes, 'role' => $roleName]])[0];
    }

    protected function cleanUpTenants(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        foreach ($this->tenantsToCleanUp as $slug) {
            $database = 'tenant_'.str_replace('-', '_', $slug);

            try {
                DB::connection('tenant_template')->statement("DROP DATABASE IF EXISTS `{$database}`");
            } catch (\Throwable) {
                // Best-effort cleanup — a leftover test database is not worth failing the suite over.
            }
        }

        // Stancl's runtime "tenant" connection persists in Laravel's connection
        // resolver across tests within the same PHPUnit process. Left in place,
        // the next test class's RefreshDatabase setup tries to wrap a stale,
        // already-dropped tenant database in a transaction, which corrupts the
        // central sqlite :memory: connection's own transaction/migration state.
        DB::purge('tenant');
    }
}
