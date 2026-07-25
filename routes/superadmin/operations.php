<?php

use App\Http\Controllers\Admin\OperationController;
use App\Http\Controllers\Admin\OperationsResourceController;
use Illuminate\Support\Facades\Route;

Route::get('operations', [OperationController::class, 'index'])->middleware('central.permission:operations.view')->name('operations.index');
Route::get('operations/health', [OperationsResourceController::class, 'health'])->middleware('central.permission:operations.view')->name('operations.health');
Route::get('operations/{operation}', [OperationController::class, 'show'])->middleware('central.permission:operations.view')->name('operations.show');
Route::get('operations-tools/{resource}', OperationsResourceController::class)
    ->whereIn('resource', ['queues', 'failed-jobs', 'scheduler', 'webhooks', 'api-logs', 'backups', 'maintenance', 'incidents'])
    ->middleware('central.permission:operations.view')
    ->name('operations.resource.index');
