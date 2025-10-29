<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Main job runs hourly - using Playwright to bypass Vercel security checkpoint
Schedule::job(new \App\Jobs\PlaywrightFetchJobsFromHiringCafe)
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/hiring_cafe_jobs_playwright.log'));

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

// Health monitoring - runs every 15 minutes
Schedule::command('scheduler:monitor')
    ->everyFifteenMinutes()
    ->withoutOverlapping(5);

// Send job match digest emails - runs hourly to check all timezones
Schedule::job(new \App\Jobs\SendJobMatchDigests)
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/job_match_digests.log'));
