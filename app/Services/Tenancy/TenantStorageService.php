<?php

namespace App\Services\Tenancy;

use RuntimeException;

class TenantStorageService
{
    public function path(string $path = ''): string
    {
        if (! tenancy()->initialized) {
            throw new RuntimeException('Tenant storage can only be resolved inside tenant context.');
        }

        $clean = trim(str_replace(['..', '\\'], ['', '/'], $path), '/');

        return storage_path('app/tenants/'.tenant('id').($clean === '' ? '' : '/'.$clean));
    }
}
