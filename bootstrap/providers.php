<?php

use App\Providers\AppServiceProvider;
use App\Providers\KnowledgeServiceProvider;
use App\Providers\TenancyServiceProvider;
use App\Providers\TenantPermissionServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    TenantPermissionServiceProvider::class,
    KnowledgeServiceProvider::class,
];
