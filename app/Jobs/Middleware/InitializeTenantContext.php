<?php

namespace App\Jobs\Middleware;

use App\Enums\TenantStatus;
use App\Models\Tenant;

class InitializeTenantContext
{
    public function __construct(private readonly ?string $tenantId) {}

    public function handle(object $job, callable $next): void
    {
        if (! $this->tenantId) {
            $next($job);

            return;
        }

        // With QUEUE_CONNECTION=sync, jobs run inline within whatever request
        // dispatched them — which, for a job dispatched from a tenant-scoped
        // controller, is itself already running inside an initialized tenancy.
        // Only initialize/end here if this job is the one establishing it, so
        // a synchronously-run job never tears down the caller's tenancy.
        if (tenancy()->initialized && tenant('id') === $this->tenantId) {
            $next($job);

            return;
        }

        $tenant = Tenant::find($this->tenantId);

        if (! $tenant || $tenant->status === TenantStatus::Deleted || $tenant->status === TenantStatus::Suspended) {
            $job->fail('Tenant is not available for queued job execution.');

            return;
        }

        tenancy()->initialize($tenant);

        try {
            $next($job);
        } finally {
            tenancy()->end();
        }
    }
}
