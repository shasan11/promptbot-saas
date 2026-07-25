<?php

use App\Http\Controllers\Admin\WebsiteResourceController;
use Illuminate\Support\Facades\Route;

Route::get('website/{resource?}', WebsiteResourceController::class)
    ->middleware('central.permission:website.view')
    ->name('website.resource.index');
