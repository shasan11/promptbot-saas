<?php

use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('settings/{group?}', [SettingsController::class, 'edit'])->middleware('central.permission:settings.view')->name('settings.edit');
Route::put('settings/{group}', [SettingsController::class, 'update'])->middleware('central.permission:settings.update')->name('settings.update');
