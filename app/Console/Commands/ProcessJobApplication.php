<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PlaywrightAutomationService;

class ProcessJobApplication extends Command
{
    protected $signature = 'job:process-application 
                           {job_url : The job URL to process}
                           {user_id : The user ID}
                           {--form-data= : JSON encoded form data}';

    protected $description = 'Process job application using Playwright automation via CLI';

    public function handle()
    {
        $jobUrl = $this->argument('job_url');
        $userId = $this->argument('user_id');
        $formDataJson = $this->option('form-data') ?? '{}';
        
        try {
            $formData = json_decode($formDataJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in form-data option');
                return 1;
            }

            $this->info("Processing job application via CLI...");
            $this->info("URL: {$jobUrl}");
            $this->info("User ID: {$userId}");

            $service = app(PlaywrightAutomationService::class);
            
            $startTime = microtime(true);
            $result = $service->processJobApplicationWithLLM($jobUrl, $userId, $formData);
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info("Automation completed in {$duration} seconds");
            
            // Output result as JSON so the API can parse it
            $this->line('RESULT_START');
            $this->line(json_encode($result));
            $this->line('RESULT_END');
            
            return $result['status'] === 'ready_to_submit' ? 0 : 1;
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            
            // Output error result as JSON
            $this->line('RESULT_START');
            $this->line(json_encode([
                'status' => 'error',
                'error' => $e->getMessage()
            ]));
            $this->line('RESULT_END');
            
            return 1;
        }
    }
}