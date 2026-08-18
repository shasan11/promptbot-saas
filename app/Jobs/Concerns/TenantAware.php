<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\InitializeTenantContext;
use App\Models\Tenant;
use Closure;

trait TenantAware
{
    public ?string $tenantId = null;

    public function captureTenant(): void
    {
        $this->tenantId = tenancy()->initialized ? (string) tenant('id') : null;
    }

    public function middleware(): array
    {
        return [new InitializeTenantContext($this->tenantId)];
    }

    /**
     * Ensures tenant-scoped work executes against the correct tenant database.
     *
     * Laravel does not run a job's middleware() stack around its failed()
     * callback, so code that touches tenant models from failed() cannot rely
     * on InitializeTenantContext having run. Left unguarded, a long-running
     * worker process could execute failed() with whatever tenant connection
     * a *previous*, unrelated job happened to leave active — silently writing
     * into the wrong tenant's database. This re-establishes the job's own
     * tenant explicitly and restores whatever tenancy was active before, so
     * a call made mid-request (e.g. under the sync queue driver) never tears
     * down its caller's context.
     */
    protected function runInTenantContext(Closure $callback): mixed
    {
        if (! $this->tenantId) {
            return $callback();
        }

        if (tenancy()->initialized && (string) tenant('id') === $this->tenantId) {
            return $callback();
        }

        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            return null;
        }

        $previousTenant = tenancy()->initialized ? tenant() : null;

        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();

            if ($previousTenant) {
                tenancy()->initialize($previousTenant);
            }
        }
    }
}
