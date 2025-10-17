<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Main job runs hourly - TEMPORARILY DISABLED DUE TO 403 IP BAN COOLDOWN
// Schedule::command('hiring-cafe:fetch-jobs')
//     ->hourly()
//     ->withoutOverlapping(30)
//     ->appendOutputTo(storage_path('logs/hiring_cafe_jobs.log'));

// Discover new job matches and notify users - runs hourly
Schedule::job(new \App\Jobs\DiscoverNewMatches)
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/discover_new_matches.log'));

// Scheduler heartbeat - runs every minute to track scheduler health
Schedule::call(function () {
    \Illuminate\Support\Facades\Cache::put('scheduler_heartbeat', now(), 3600);
    \Illuminate\Support\Facades\Log::info('Scheduler heartbeat: ' . now()->format('Y-m-d H:i:s'));
})->everyMinute();

// Health monitoring - runs every 15 minutes - DISABLED
// Schedule::command('scheduler:monitor')
//     ->everyFifteenMinutes()
//     ->withoutOverlapping(5);
