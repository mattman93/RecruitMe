<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class MonitorSchedulerHealth extends Command
{
    protected $signature = 'scheduler:monitor {--force : Force immediate check regardless of cache}';
    protected $description = 'Monitor scheduler health and detect missed jobs';

    public function handle()
    {
        $this->info('Checking scheduler health...');
        
        // Check if we should run (prevent spam)
        $cacheKey = 'scheduler_health_last_check';
        $lastCheck = Cache::get($cacheKey);
        
        if (!$this->option('force') && $lastCheck && $lastCheck->diffInMinutes(now()) < 5) {
            $this->info('Health check ran recently, skipping...');
            return;
        }
        
        Cache::put($cacheKey, now(), 3600); // Cache for 1 hour
        
        $issues = [];
        
        // 1. Check for missed jobs
        $missedJobs = $this->checkForMissedJobs();
        if ($missedJobs > 0) {
            $issues[] = "Found {$missedJobs} missed job(s)";
            $this->runMissedJobs();
        }
        
        // 2. Check scheduler heartbeat
        $heartbeatIssue = $this->checkSchedulerHeartbeat();
        if ($heartbeatIssue) {
            $issues[] = $heartbeatIssue;
        }
        
        // 3. Check data source health
        $dataSourceIssue = $this->checkDataSourceHealth();
        if ($dataSourceIssue) {
            $issues[] = $dataSourceIssue;
        }
        
        if (empty($issues)) {
            $this->info('✅ Scheduler health check passed');
            Log::info('Scheduler health check: All systems operational');
        } else {
            $this->error('⚠️ Scheduler health issues detected:');
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
                Log::warning("Scheduler health issue: {$issue}");
            }
            
            $this->sendHealthAlert($issues);
        }
    }
    
    private function checkForMissedJobs(): int
    {
        $dataSource = DataSource::where('name', 'hiring.cafe')->first();
        if (!$dataSource) {
            return 0;
        }
        
        $lastFetch = $dataSource->last_fetched_at;
        if (!$lastFetch) {
            return 1; // Never run
        }
        
        // Calculate how many hourly jobs should have run since last fetch
        $hoursSinceLastFetch = $lastFetch->diffInHours(now());
        $expectedRuns = floor($hoursSinceLastFetch);
        
        // Allow 10 minute grace period for current hour
        if (now()->minute < 10) {
            $expectedRuns = max(0, $expectedRuns - 1);
        }
        
        return max(0, $expectedRuns);
    }
    
    private function checkSchedulerHeartbeat(): ?string
    {
        $heartbeatKey = 'scheduler_heartbeat';
        $lastHeartbeat = Cache::get($heartbeatKey);
        
        if (!$lastHeartbeat) {
            return 'No scheduler heartbeat found';
        }
        
        $minutesSinceHeartbeat = $lastHeartbeat->diffInMinutes(now());
        
        if ($minutesSinceHeartbeat > 70) { // Allow 10 minute grace period
            return "Scheduler heartbeat is {$minutesSinceHeartbeat} minutes old";
        }
        
        return null;
    }
    
    private function checkDataSourceHealth(): ?string
    {
        $dataSource = DataSource::where('name', 'hiring.cafe')->first();
        if (!$dataSource) {
            return 'hiring.cafe data source not found';
        }
        
        if (!$dataSource->is_active) {
            return 'hiring.cafe data source is inactive';
        }
        
        return null;
    }
    
    private function runMissedJobs(): void
    {
        $this->info('🔄 Running missed jobs...');

        // Dispatch the job immediately
        \App\Jobs\PlaywrightFetchJobsFromHiringCafe::dispatch();

        Log::info('Dispatched missed PlaywrightFetchJobsFromHiringCafe job');
        $this->info('✅ Missed job dispatched');
    }
    
    private function sendHealthAlert(array $issues): void
    {
        // Log health issues instead of sending email
        Log::warning('🚨 AppliFlow Scheduler Health Alert', [
            'issues' => $issues,
            'actions_taken' => [
                'Attempted to dispatch missed jobs',
                'Health monitoring will continue'
            ]
        ]);

        // Only send email for critical scheduler failures (multiple missed jobs)
        $missedJobsCount = 0;
        foreach ($issues as $issue) {
            if (preg_match('/Found (\d+) missed job/', $issue, $matches)) {
                $missedJobsCount = (int)$matches[1];
            }
        }

        // Send email only if more than 3 jobs were missed (indicates serious problem)
        if ($missedJobsCount > 3) {
            $subject = '🚨 AppliFlow Scheduler Health Alert - CRITICAL';
            $message = "
                <h2>🚨 Scheduler Health Issues Detected</h2>
                <p><strong>Issues found:</strong></p>
                <ul>
                    " . implode('', array_map(fn($issue) => "<li>{$issue}</li>", $issues)) . "
                </ul>
                <p><strong>Timestamp:</strong> " . now()->format('Y-m-d H:i:s T') . "</p>
                <p><strong>Automatic Actions Taken:</strong></p>
                <ul>
                    <li>Attempted to dispatch missed jobs</li>
                    <li>Health monitoring will continue</li>
                </ul>

                <hr>
                <p><em>This is an automated health alert from AppliFlow monitoring system.</em></p>
            ";

            try {
                Mail::raw(strip_tags($message), function ($mail) use ($subject, $message) {
                    $mail->to('mattcieslak93@gmail.com')
                         ->subject($subject)
                         ->html($message);
                });

                Log::info('Critical scheduler health alert email sent');
            } catch (\Exception $e) {
                Log::error('Failed to send health alert email: ' . $e->getMessage());
            }
        }
    }
}