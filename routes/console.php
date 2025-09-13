<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Main job runs hourly
Schedule::command('hiring-cafe:fetch-jobs')
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/hiring_cafe_jobs.log'));

// Daily midnight job for comprehensive data collection
Schedule::command('hiring-cafe:fetch-jobs')
    ->dailyAt('00:00')
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/hiring_cafe_midnight.log'));
