<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PlaywrightAutomationService;

class TestBotEvasion extends Command
{
    protected $signature = 'test:bot-evasion {job_url} {user_id}';
    protected $description = 'Test bot evasion techniques against job application forms';

    public function handle()
    {
        $jobUrl = $this->argument('job_url');
        $userId = $this->argument('user_id');
        
        $this->info("Testing bot evasion for job: {$jobUrl}");
        $this->info("User ID: {$userId}");
        
        $service = new PlaywrightAutomationService();
        
        // Create test session data
        $sessionData = [
            'job_url' => $jobUrl,
            'form_data' => [
                'first_name' => 'John',
                'last_name' => 'Doe', 
                'email' => 'john.doe@example.com',
                'phone' => '555-1234',
                'streetAddress' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'zip' => '12345'
            ]
        ];
        
        try {
            // Use reflection to call the protected method
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('executeFormSubmission');
            $method->setAccessible(true);
            
            $this->info("Starting form submission with bot evasion...");
            $result = $method->invoke($service, $sessionData, $userId);
            
            $this->info("Result: " . json_encode($result, JSON_PRETTY_PRINT));
            
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
        }
    }
}