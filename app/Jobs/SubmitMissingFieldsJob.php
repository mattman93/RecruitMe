<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SubmitMissingFieldsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes timeout for the job

    protected $sessionId;
    protected $missingFieldValues;
    protected $userId;
    protected $responseSessionKey;

    public function __construct($sessionId, $missingFieldValues, $userId, $responseSessionKey)
    {
        $this->sessionId = $sessionId;
        $this->missingFieldValues = $missingFieldValues;
        $this->userId = $userId;
        $this->responseSessionKey = $responseSessionKey;
    }

    public function handle()
    {
        Log::info('Processing missing fields submission in background', [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'response_session_key' => $this->responseSessionKey,
            'fields_count' => count($this->missingFieldValues)
        ]);

        try {
            // Update status to processing
            Cache::put($this->responseSessionKey, [
                'status' => 'processing',
                'message' => 'Submitting missing fields and continuing application...',
                'started_at' => now()->toISOString()
            ], 600); // 10 minutes

            // Use the specific CLI command for submitting missing fields
            $missingFieldsJson = json_encode($this->missingFieldValues);
            
            $startTime = microtime(true);
            $result = \Illuminate\Support\Facades\Process::timeout(300)->run([
                'php', 'artisan', 'job:submit-missing-fields',
                $this->sessionId,
                $this->userId,
                '--missing-fields=' . $missingFieldsJson
            ]);
            $duration = round(microtime(true) - $startTime, 2);
            
            // Parse the result from CLI output
            $output = $result->output();
            if (preg_match('/RESULT_START\s*(.*?)\s*RESULT_END/s', $output, $matches)) {
                $parsedResult = json_decode($matches[1], true);
                if (!$parsedResult) {
                    throw new \Exception('Failed to parse CLI result JSON');
                }
                $result = $parsedResult;
            } else {
                if (!$result->successful()) {
                    throw new \Exception('CLI command failed: ' . $result->errorOutput());
                } else {
                    throw new \Exception('CLI result not found in output');
                }
            }

            // Store the final result
            $finalResult = array_merge($result, [
                'duration' => $duration,
                'completed_at' => now()->toISOString()
            ]);

            Cache::put($this->responseSessionKey, $finalResult, 600); // 10 minutes

            Log::info('Background missing fields submission completed', [
                'response_session_key' => $this->responseSessionKey,
                'status' => $result['status'],
                'duration' => $duration
            ]);

        } catch (\Exception $e) {
            Log::error('Background missing fields submission failed', [
                'response_session_key' => $this->responseSessionKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Store error result
            Cache::put($this->responseSessionKey, [
                'status' => 'error',
                'error' => $e->getMessage(),
                'completed_at' => now()->toISOString()
            ], 600);
        }
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Missing fields submission job failed completely', [
            'response_session_key' => $this->responseSessionKey,
            'error' => $exception->getMessage()
        ]);

        Cache::put($this->responseSessionKey, [
            'status' => 'error',
            'error' => 'Missing fields submission failed: ' . $exception->getMessage(),
            'completed_at' => now()->toISOString()
        ], 600);
    }
}