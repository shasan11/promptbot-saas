<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->group(function (): void {
    Route::get('/tenant', fn () => [
        'tenant_id' => tenant('id'),
        'status' => tenant('status')?->value ?? tenant('status'),
    ]);
});
