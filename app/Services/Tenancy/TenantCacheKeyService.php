<?php

namespace App\Services\Tenancy;

class TenantCacheKeyService
{
    public function key(string $key): string
    {
        return tenancy()->initialized
            ? sprintf('tenant:%s:%s', tenant('id'), ltrim($key, ':'))
            : 'central:'.ltrim($key, ':');
    }
}
