<?php

namespace App\Console\Commands;

use App\Jobs\PlaywrightFetchJobsFromHiringCafe;
use Illuminate\Console\Command;

class FetchJobsFromHiringCafeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hiring-cafe:fetch-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch jobs from hiring.cafe using Playwright (bypasses Vercel security)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching PlaywrightFetchJobsFromHiringCafe job...');

        dispatch(new PlaywrightFetchJobsFromHiringCafe());

        $this->info('Job dispatched successfully! Check logs at storage/logs/hiring_cafe_jobs_playwright.log');

        return 0;
    }
}
