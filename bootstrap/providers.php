<?php

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\TenantPermissionServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    TenantPermissionServiceProvider::class,
];
