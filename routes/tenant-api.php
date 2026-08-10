<?php

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use App\Http\Controllers\Tenant\Api\V1\ConnectionUsageController;
use App\Http\Controllers\Tenant\Api\V1\ResourceController;
use App\Http\Controllers\Tenant\Api\V1\AIAgentController;

Route::middleware([
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'tenant.active',
])->group(function (): void {
    Route::get('/tenant', fn () => [
        'tenant_id' => tenant('id'),
        'status' => tenant('status')?->value ?? tenant('status'),
    ]);
    Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
        Route::get('contacts', [ResourceController::class, 'contacts'])->middleware('developer.key:contacts.read');
        Route::get('conversations', [ResourceController::class, 'conversations'])->middleware('developer.key:conversations.read');
        Route::get('tickets', [ResourceController::class, 'tickets'])->middleware('developer.key:tickets.read');
        Route::get('connections/{connection}/usage', [ConnectionUsageController::class, 'show'])->middleware('developer.key:reports.read');
        Route::get('ai/agents', [AIAgentController::class, 'index'])->middleware(['tenant.feature:ai_platform','developer.key:ai.read']);
        Route::post('ai/agents/{agent}/run', [AIAgentController::class, 'run'])->middleware(['tenant.feature:ai_platform','developer.key:ai.run','throttle:20,1']);
    });
});
