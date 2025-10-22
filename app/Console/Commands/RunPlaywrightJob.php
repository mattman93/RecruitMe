<?php

namespace App\Console\Commands;

use App\Jobs\PlaywrightFetchJobsFromHiringCafe;
use Illuminate\Console\Command;

class RunPlaywrightJob extends Command
{
    protected $signature = 'playwright:run-job';
    protected $description = 'Run the Playwright HiringCafe job directly (for testing)';

    public function handle()
    {
        $this->info('Running PlaywrightFetchJobsFromHiringCafe job...');

        $job = new PlaywrightFetchJobsFromHiringCafe();
        $job->handle();

        $this->info('Job completed!');

        return 0;
    }
}
