<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\JobApplicationService;
use App\Models\User;
use App\Models\Lead;

class ProcessJobApplication extends Command
{
    protected $signature = 'job:process-application 
                           {job_url : The job URL to process}
                           {user_id : The user ID}
                           {--form-data= : JSON encoded form data}';

    protected $description = 'Process job application using email-based strategy via CLI';

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

            // Find the user
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");
                return 1;
            }

            // Find the lead by source URL
            $lead = Lead::where('source_url', $jobUrl)->first();
            if (!$lead) {
                $this->error("Lead not found with URL: {$jobUrl}");
                return 1;
            }

            $service = app(JobApplicationService::class);

            $startTime = microtime(true);

            // Parse custom responses from form data
            $customResponses = $formData['custom_responses'] ?? [];

            // Queue and process the application using email-based strategy
            $application = $service->queueApplication($user, $lead, $customResponses);
            $result = $service->processApplication($application);

            $duration = round(microtime(true) - $startTime, 2);

            $this->info("Email-based application completed in {$duration} seconds");

            // Output result as JSON so the API can parse it
            $this->line('RESULT_START');
            $this->line(json_encode([
                'status' => $result ? 'submitted' : 'failed',
                'application_id' => $application->id,
                'method' => 'email_based',
                'message' => $result ? 'Application email sent successfully' : 'Application failed'
            ]));
            $this->line('RESULT_END');

            return $result ? 0 : 1;
            
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