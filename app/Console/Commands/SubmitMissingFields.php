<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PlaywrightAutomationService;

class SubmitMissingFields extends Command
{
    protected $signature = 'job:submit-missing-fields 
                           {session_id : The session ID}
                           {user_id : The user ID}
                           {--missing-fields= : JSON encoded missing field values}';

    protected $description = 'Submit missing fields and continue job application automation via CLI';

    public function handle()
    {
        $sessionId = $this->argument('session_id');
        $userId = $this->argument('user_id');
        $missingFieldsJson = $this->option('missing-fields') ?? '{}';
        
        try {
            $missingFields = json_decode($missingFieldsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON in missing-fields option');
                return 1;
            }

            $this->info("Submitting missing fields via CLI...");
            $this->info("Session ID: {$sessionId}");
            $this->info("User ID: {$userId}");
            $this->info("Missing fields count: " . count($missingFields));

            $service = app(PlaywrightAutomationService::class);
            
            $startTime = microtime(true);
            $result = $service->submitMissingFieldsAndContinue($sessionId, $missingFields, $userId);
            $duration = round(microtime(true) - $startTime, 2);
            
            $this->info("Missing fields submission completed in {$duration} seconds");
            
            // Output result as JSON so the job can parse it
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