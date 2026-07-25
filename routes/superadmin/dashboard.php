<?php

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', DashboardController::class)
    ->middleware('central.permission:dashboard.view')
    ->name('dashboard');
