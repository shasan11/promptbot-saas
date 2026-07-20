<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\InitializeTenantContext;

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
}
