<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Knowledge Base module
|--------------------------------------------------------------------------
|
| Both commands walk every active tenant, so they are guarded against overlap:
| a sweep that runs long must not have a second copy start behind it and
| double-dispatch the same sources.
|
*/

Schedule::command('knowledge:sync-sources')
    ->everyFifteenMinutes()
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('knowledge:release-stale-jobs')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground();
