<?php

namespace App\Console\Commands;

use App\Jobs\FetchJobsFromHiringCafe;
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
    protected $description = 'Fetch jobs from hiring.cafe and store them in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching FetchJobsFromHiringCafe job...');
        
        dispatch(new FetchJobsFromHiringCafe());
        
        $this->info('Job dispatched successfully!');
        
        return 0;
    }
}
