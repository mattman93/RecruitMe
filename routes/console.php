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

// Process auto-apply for Pro users - runs hourly and checks each user's frequency settings
Schedule::job(new \App\Jobs\ProcessAutoApply)
    ->hourly()
    ->withoutOverlapping(30)
    ->appendOutputTo(storage_path('logs/auto_apply.log'));

// Send daily auto-apply digest - runs at 7pm daily
Schedule::job(new \App\Jobs\SendAutoApplyDigest('daily'))
    ->dailyAt('19:00')
    ->appendOutputTo(storage_path('logs/auto_apply_digest.log'));

// Send weekly auto-apply digest - runs Sundays at 7pm
Schedule::job(new \App\Jobs\SendAutoApplyDigest('weekly'))
    ->weeklyOn(0, '19:00') // 0 = Sunday
    ->appendOutputTo(storage_path('logs/auto_apply_digest.log'));

// Sync leads from local to production - runs hourly (LOCAL ONLY)
Schedule::command('leads:sync-to-production --hours=1')
    ->hourly()
    ->when(fn() => !app()->environment('production'))
    ->appendOutputTo(storage_path('logs/lead_sync.log'));

// Import leads from latest export - runs every 15 minutes (PRODUCTION ONLY)
Schedule::job(new \App\Jobs\ImportLeadsFromLatestExport)
    ->everyFifteenMinutes()
    ->when(fn() => app()->environment('production'))
    ->appendOutputTo(storage_path('logs/lead_import.log'));
