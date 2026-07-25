<?php

use App\Http\Controllers\Admin\CommunicationResourceController;
use Illuminate\Support\Facades\Route;

Route::get('communications/{resource?}', CommunicationResourceController::class)
    ->middleware('central.permission:communications.view')
    ->name('communications.resource.index');
