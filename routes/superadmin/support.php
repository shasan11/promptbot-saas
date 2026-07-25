<?php

use App\Http\Controllers\Admin\SupportController;
use Illuminate\Support\Facades\Route;

Route::get('support', [SupportController::class, 'index'])->middleware('central.permission:support.view')->name('support.index');
Route::get('support/{ticket}', [SupportController::class, 'show'])->middleware('central.permission:support.view')->name('support.show');
