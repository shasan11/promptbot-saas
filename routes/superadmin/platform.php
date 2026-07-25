<?php

use App\Http\Controllers\Admin\FeatureController;
use App\Http\Controllers\Admin\PlatformResourceController;
use Illuminate\Support\Facades\Route;

Route::resource('features', FeatureController::class)->middleware('central.permission:features.view');
Route::get('platform/{resource}', PlatformResourceController::class)
    ->whereIn('resource', ['usage', 'integrations', 'ai-models', 'provider-health', 'feature-flags'])
    ->middleware('central.permission:integrations.view')
    ->name('platform.resource.index');
